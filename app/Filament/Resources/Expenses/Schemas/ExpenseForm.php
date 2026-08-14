<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\HouseholdRole;
use App\Enums\LabelType;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Support\SelectValueMarquee;
use App\Helpers\MoneyDisplay;
use App\Helpers\UserDateDisplay;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Currency\CurrencyConversionException;
use App\Services\ManualExpenseCurrencyConversionService;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class ExpenseForm
{
    /**
     * @var list<string>
     */
    private const SOURCE_CURRENCIES = [
        'USD',
        'SGD',
        'AUD',
        'CAD',
        'HKD',
        'NZD',
        'EUR',
        'GBP',
        'JPY',
        'CNY',
        'THB',
        'IDR',
        'INR',
        'KRW',
        'PHP',
        'VND',
        'CHF',
        'AED',
        'SAR',
        'BRL',
        'ZAR',
    ];

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Image & Uploads', 'id' => 'image-uploads'],
            ['label' => 'Receipt Details', 'id' => 'receipt-details'],
            ['label' => 'Expense Notes', 'id' => 'expense-notes'],
            ['label' => 'Line Items', 'id' => 'line-items'],
            ['label' => 'Expense Status', 'id' => 'expense-status'],
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        $conversionMetadataVisible = static function (Get $get, ?Expense $record): bool {
            $status = $get('currency_conversion_status');

            if (filled($status)) {
                return $status !== Expense::CONVERSION_NOT_REQUIRED;
            }

            return $record instanceof Expense
                && $record->currency_conversion_status !== Expense::CONVERSION_NOT_REQUIRED;
        };

        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->columnOrder([
                        'default' => 2,
                        'lg' => 1,
                    ])
                    ->extraAttributes(['class' => 'fi-expense-main-column'])
                    ->schema([
                        Section::make('Receipt Details')
                            ->id('receipt-details')
                            ->schema([
                                Hidden::make('currency_conversion_status')
                                    ->default(Expense::CONVERSION_NOT_REQUIRED),
                                Hidden::make('currency_conversion_fetched_at'),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('merchant_name')
                                            ->required()
                                            ->placeholder('Merchant name'),

                                        TextInput::make('invoice_number')
                                            ->placeholder('Invoice number'),

                                        DateTimePicker::make('date_time')
                                            ->required()
                                            ->seconds(false)
                                            ->displayFormat(fn (): string => UserDateDisplay::dateTimeFormat())
                                            ->timezone(fn (): string => UserDateDisplay::timezone())
                                            ->default(now()),
                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->myr()
                                            ->required()
                                            ->placeholder('0.00'),

                                        TextInput::make('total_tax')
                                            ->label('Tax / Service')
                                            ->myr()
                                            ->default(0.00),

                                        TextInput::make('discount_total')
                                            ->myr()
                                            ->default(0.00),
                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('rounding_amount')
                                            ->myr()
                                            ->default(0.00)
                                            ->helperText('May be negative'),

                                        TextInput::make('total_amount')
                                            ->myr()
                                            ->required()
                                            ->placeholder('0.00'),

                                        Select::make('currency')
                                            ->options(function (Get $get): array {
                                                $options = [
                                                    'MYR' => 'MYR (Malaysian Ringgit)',
                                                ];

                                                foreach (self::SOURCE_CURRENCIES as $sourceCurrency) {
                                                    $options[$sourceCurrency] = $sourceCurrency.' (source; conversion required)';
                                                }

                                                $currency = strtoupper(trim((string) $get('currency')));

                                                if ($currency !== '' && $currency !== 'MYR' && ! array_key_exists($currency, $options)) {
                                                    $options[$currency] = $currency.' (source; conversion required)';
                                                }

                                                return $options;
                                            })
                                            ->default('MYR')
                                            ->live()
                                            ->searchable()
                                            ->required()
                                            ->wrapOptionLabels(false)
                                            ->extraAttributes(SelectValueMarquee::extraAttributes()),

                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('original_currency')
                                            ->label('Original Currency')
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible($conversionMetadataVisible),

                                        TextInput::make('original_total_amount')
                                            ->label('Original Amount')
                                            ->prefix(fn (Get $get): string => MoneyDisplay::prefixForCurrency($get('original_currency')))
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible($conversionMetadataVisible),

                                        TextInput::make('currency_conversion_rate')
                                            ->label('Rate (MYR per unit)')
                                            ->suffix('MYR')
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible($conversionMetadataVisible),

                                        TextInput::make('currency_conversion_date')
                                            ->label('Rate Date')
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible($conversionMetadataVisible),

                                        TextInput::make('currency_conversion_provider')
                                            ->label('Rate Provider')
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible($conversionMetadataVisible),

                                        Actions::make([
                                            Action::make('convertCurrency')
                                                ->label('Convert to MYR')
                                                ->icon(Heroicon::ArrowsRightLeft)
                                                ->color('primary')
                                                ->button()
                                                ->action(function (
                                                    array $schemaState,
                                                    Get $schemaGet,
                                                    Set $schemaSet,
                                                    ManualExpenseCurrencyConversionService $conversionService,
                                                ): void {
                                                    try {
                                                        $schemaState['expenseItems'] = $schemaGet('expenseItems') ?? [];
                                                        $convertedState = $conversionService->convert($schemaState);
                                                    } catch (CurrencyConversionException $exception) {
                                                        Notification::make()
                                                            ->title('Currency conversion failed')
                                                            ->body($exception->getMessage())
                                                            ->danger()
                                                            ->send();

                                                        return;
                                                    }

                                                    foreach ($convertedState as $key => $value) {
                                                        $schemaSet($key, $value);
                                                    }

                                                    Notification::make()
                                                        ->title('Currency converted to MYR')
                                                        ->body('Amounts were converted using the receipt-date exchange rate.')
                                                        ->success()
                                                        ->send();
                                                }),
                                        ])
                                            ->key('currencyConversionActions')
                                            ->alignment(Alignment::End)
                                            ->columnStart([
                                                'default' => 1,
                                                'lg' => 3,
                                            ])
                                            ->visible(fn (Get $schemaGet): bool => self::isForeignCurrency($schemaGet('currency'))),
                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        Select::make('payment_method_id')
                                            ->label('Payment Method')
                                            ->relationship('paymentMethod', 'name')
                                            ->searchable()
                                            ->preload(),

                                        Select::make('source')
                                            ->options([
                                                'manual' => 'Manual Upload',
                                                'whatsapp' => 'WhatsApp',
                                            ])
                                            ->default('manual')
                                            ->searchable()
                                            ->required(),

                                        Select::make('family_member_id')
                                            ->label('Uploaded By')
                                            ->options(fn (): array => FamilyMember::query()
                                                ->orderBy('name')
                                                ->get(['id', 'name', 'display_name'])
                                                ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                                                    $familyMember->getKey() => filled($familyMember->display_name)
                                                        ? (string) $familyMember->display_name
                                                        : (string) $familyMember->name,
                                                ])
                                                ->all())
                                            ->placeholder(fn (): string => self::primaryUsername())
                                            ->searchable()
                                            ->nullable()
                                            ->disabled(fn (): bool => HouseholdAccess::isFamilyMember())
                                            ->dehydrated(fn (): bool => ! HouseholdAccess::isFamilyMember()),
                                    ]),
                            ]),

                        Section::make('Expense Notes')
                            ->id('expense-notes')
                            ->schema([
                                NotesRichEditor::make('notes')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Line Items')
                            ->id('line-items')
                            ->schema([
                                Repeater::make('expenseItems')
                                    ->relationship('expenseItems')
                                    ->schema([
                                        TextInput::make('description')
                                            ->required()
                                            ->default('Item name')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state('Item name');
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Grid::make(4)
                                            ->schema([
                                                Select::make('label_id')
                                                    ->label('Label')
                                                    ->relationship(
                                                        name: 'label',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn ($query) => $query->where('type', LabelType::Finance)->orderBy('name'),
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->columnSpan(2),

                                                TextInput::make('quantity')
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->default(1)
                                                    ->required()
                                                    ->helperText('Supports kg / litres')
                                                    ->columnSpan(1),

                                                TextInput::make('unit_price')
                                                    ->myr()
                                                    ->required()
                                                    ->columnSpan(1),
                                            ]),

                                        Grid::make(6)
                                            ->schema([
                                                TextInput::make('line_total')
                                                    ->myr()
                                                    ->required()
                                                    ->default('0.00')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (TextInput $component, mixed $state): void {
                                                        if (blank($state)) {
                                                            $component->state('0.00');
                                                        }
                                                    })
                                                    ->columnSpan(2),

                                                DatePicker::make('warranty_expiry_date')
                                                    ->columnSpan(2),

                                                TextInput::make('serial_number')
                                                    ->columnSpan(2),
                                            ]),
                                    ])
                                    ->itemLabel(function (array $state): ?string {
                                        $description = $state['description'] ?? null;

                                        if (blank($description)) {
                                            return null;
                                        }

                                        $lineTotal = $state['line_total'] ?? null;

                                        if ($lineTotal === null || $lineTotal === '') {
                                            return $description;
                                        }

                                        return sprintf('%s (%s)', $description, MoneyDisplay::withPrefix($lineTotal, spaceAfterPrefix: false));
                                    })
                                    ->collapsed()
                                    ->columns(1),
                            ]),

                        Section::make('Expense Status')
                            ->id('expense-status')
                            ->schema([
                                Select::make('status')
                                    ->hiddenLabel()
                                    ->options([
                                        'pending' => 'Pending Parsing',
                                        'parsed' => 'Parsed by AI',
                                        'reviewed' => 'Reviewed',
                                        'requires_manual_review' => 'Requires Manual Review',
                                        'failed' => 'Parsing Failed',
                                    ])
                                    ->default('pending')
                                    ->searchable()
                                    ->required(),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->columnOrder([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->extraAttributes(['class' => 'fi-expense-sidebar-sticky'])
                    ->schema([
                        Section::make('Image & Uploads')
                            ->id('image-uploads')
                            ->schema([
                                FileUpload::make('image_path')
                                    ->label('Receipt Image')
                                    ->image()
                                    ->maxSize(10240)
                                    ->directory('receipts')
                                    ->visibility('private')
                                    ->openable()
                                    ->downloadable()
                                    ->extraAttributes(['class' => 'fi-receipt-image-upload']),
                            ]),
                    ]),
            ]);
    }

    protected static function primaryUsername(): string
    {
        $primaryUser = User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name', 'display_name']);

        if (! $primaryUser instanceof User) {
            return 'Primary username';
        }

        return filled($primaryUser->display_name)
            ? (string) $primaryUser->display_name
            : (string) $primaryUser->name;
    }

    private static function isForeignCurrency(mixed $currency): bool
    {
        return is_string($currency)
            && preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency))) === 1
            && strtoupper(trim($currency)) !== Expense::CURRENCY_MYR;
    }
}
