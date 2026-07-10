<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\LabelingType;
use App\Enums\PaymentMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Receipt Information')
                    ->description('General invoice details')
                    ->columnSpan('full')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('merchant_name')
                                    ->required()
                                    ->helperText('Prefer store brand and branch, e.g. FamilyMart Pinggiran Batu Caves'),
                                TextInput::make('invoice_number'),
                                DateTimePicker::make('date_time')
                                    ->required()
                                    ->default(now()),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required(),

                                TextInput::make('total_tax')
                                    ->label('Tax / Service')
                                    ->helperText('SST / service tax (include service charge if not split)')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->default(0.00),

                                TextInput::make('discount_total')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->default(0.00),

                                TextInput::make('rounding_amount')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->default(0.00)
                                    ->helperText('May be negative'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required(),

                                Select::make('currency')
                                    ->options([
                                        'MYR' => 'MYR (Malaysian Ringgit)',
                                    ])
                                    ->default('MYR')
                                    ->searchable()
                                    ->required(),

                                Select::make('payment_method')
                                    ->options(PaymentMethod::class)
                                    ->searchable(),

                                Select::make('source')
                                    ->options([
                                        'manual' => 'Manual Upload',
                                        'whatsapp' => 'WhatsApp',
                                        'google_drive' => 'Google Drive',
                                    ])
                                    ->default('manual')
                                    ->searchable()
                                    ->required(),
                            ]),

                        Select::make('status')
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

                Section::make('Image & Uploads')
                    ->columnSpan('full')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Receipt Image')
                            ->image()
                            ->maxSize(10240)
                            ->directory('receipts')
                            ->visibility('private')
                            ->openable()
                            ->downloadable(),

                        Textarea::make('notes')
                            ->rows(3),
                    ]),

                Section::make('Line Items')
                    ->columnSpan('full')
                    ->schema([
                        Repeater::make('invoiceItems')
                            ->relationship('invoiceItems')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextInput::make('description')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(2),

                                        Select::make('labeling_id')
                                            ->label('Label')
                                            ->relationship(
                                                name: 'labeling',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn ($query) => $query->where('type', LabelingType::Finance),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpan(2),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->step(0.001)
                                            ->default(1)
                                            ->required()
                                            ->helperText('Supports kg / litres')
                                            ->columnSpan(1),

                                        TextInput::make('unit_price')
                                            ->numeric()
                                            ->prefix('RM')
                                            ->required()
                                            ->columnSpan(1),
                                    ]),

                                Grid::make(6)
                                    ->schema([
                                        TextInput::make('line_total')
                                            ->numeric()
                                            ->prefix('RM')
                                            ->required()
                                            ->live(onBlur: true)
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

                                return sprintf('%s (RM%s)', $description, number_format((float) $lineTotal, 2));
                            })
                            ->columns(1),
                    ]),
            ]);
    }
}
