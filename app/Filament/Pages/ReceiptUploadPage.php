<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Helpers\FilenameDisplay;
use App\Models\Invoice;
use App\Support\DashboardSpenderScope;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ReceiptUploadPage extends Page implements HasForms, HasTable
{
    use HasSectionNav;
    use InteractsWithForms;
    use InteractsWithTable;
    use PrependsHomeBreadcrumb;

    protected static ?string $slug = 'upload-receipts';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-on-square';

    protected static ?string $navigationLabel = 'Upload Receipts';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Upload Receipts';

    public ?array $data = [];

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-upload-receipts-page',
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Upload Receipts', 'id' => 'upload-receipts'],
            ['label' => 'Recent Uploads & Processing Status', 'id' => 'recent-uploads'],
        ];
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Upload receipts sections';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->wrapInSectionNavScope([
                    SchemaView::make('filament.pages.partials.receipt-upload-content'),
                ]),
            ]);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('receipts')
                    ->hiddenLabel()
                    ->multiple()
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(10240)
                    ->directory('receipts')
                    ->required()
                    ->afterStateUpdated(function (): void {
                        $this->resetValidation('data.receipts');
                    }),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $user = auth()->user();

        foreach ($state['receipts'] as $filePath) {
            $familyMemberId = $user?->family_member_id;

            $invoice = Invoice::create([
                'merchant_name' => 'Pending AI Extraction...',
                'date_time' => now(),
                'subtotal' => 0.00,
                'total_tax' => 0.00,
                'total_amount' => 0.00,
                'currency' => 'MYR',
                'source' => 'manual',
                'family_member_id' => $familyMemberId,
                'status' => 'pending',
                'receipt_hash' => hash('sha256', 'pending-upload|'.$filePath),
                'image_path' => $filePath,
                'original_filename' => basename($filePath),
                'file_mime_type' => Storage::mimeType($filePath) ?: null,
            ]);
        }

        $this->form->fill();

        Notification::make()
            ->title('Receipts uploaded successfully')
            ->body('AI extraction queue has started processing them.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Invoice::query())
            ->defaultSort('created_at', 'desc')
            ->poll('10s.visible')
            ->columns([
                FilenameDisplay::configureTextColumn(
                    TextColumn::make('original_filename')
                        ->label('Filename')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Medium)
                        ->color(fn (Invoice $record): ?string => filled($record->image_path) ? 'primary' : null)
                        ->tooltip(fn (Invoice $record): ?string => filled($record->image_path) ? (string) $record->original_filename : null)
                        ->url(
                            fn (Invoice $record): ?string => $record->fileUrl(),
                            shouldOpenInNewTab: true,
                        ),
                ),

                TextColumn::make('merchant_name')
                    ->label('Merchant')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->myr()
                    ->sortable(),

                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->badge()
                    ->icon(fn (Invoice $record): ?string => $record->paymentMethod?->icon)
                    ->placeholder('-'),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'whatsapp' => 'success',
                        'google_drive' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'parsed' => 'info',
                        'reviewed' => 'success',
                        'requires_manual_review' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (Invoice $record): bool => InvoiceResource::canEdit($record))
                    ->authorizationTooltip()
                    ->authorizationMessage(fn (Invoice $record): string => InvoicesTable::familyMemberActionAuthorizationMessage($record))
                    ->url(
                        fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]),
                    ),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Parsing',
                        'parsed' => 'Parsed by AI',
                        'reviewed' => 'Reviewed',
                        'requires_manual_review' => 'Requires Manual Review',
                        'failed' => 'Parsing Failed',
                    ])
                    ->searchable(),

                SelectFilter::make('source')
                    ->options([
                        'manual' => 'Manual',
                        'whatsapp' => 'WhatsApp',
                        'google_drive' => 'Google Drive',
                    ])
                    ->searchable(),

                SelectFilter::make('payment_method_id')
                    ->label('Payment Method')
                    ->relationship('paymentMethod', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('spender')
                    ->label('From')
                    ->options(fn (): array => DashboardSpenderScope::filterOptionsFor())
                    ->query(function (Builder $query, array $data): Builder {
                        $spender = $data['value'] ?? null;

                        if (! is_string($spender) || $spender === '' || $spender === DashboardSpenderScope::ALL) {
                            return $query;
                        }

                        if (! DashboardSpenderScope::isValid($spender)) {
                            return $query;
                        }

                        $allowed = array_keys(DashboardSpenderScope::filterOptionsFor());

                        if (! in_array($spender, $allowed, true)) {
                            return $query;
                        }

                        return (new DashboardSpenderScope($spender))->applyToInvoiceQuery($query);
                    })
                    ->searchable(),
            ])
            ->emptyStateHeading('No receipts yet')
            ->emptyStateDescription('Upload a receipt with the form above to start tracking spending.')
            ->emptyStateIcon('heroicon-o-arrow-up-tray');
    }
}
