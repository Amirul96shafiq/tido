<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Helpers\FilenameDisplay;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentReceipts extends BaseWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-receipts';

    public function table(Table $table): Table
    {
        $bounds = $this->getSelectedMonthBounds();

        return $table
            ->heading('Recent Receipts ('.$this->formatSelectedMonth('F Y').')')
            ->query(
                Invoice::query()
                    ->whereBetween('created_at', [$bounds['start'], $bounds['end']]),
            )
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25, 50])
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
            ])
            ->emptyStateHeading('No receipts')
            ->emptyStateDescription('No receipts uploaded this month.')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateActions([
                Action::make('uploadReceipts')
                    ->label('Upload Receipts')
                    ->icon(Heroicon::Plus)
                    ->url(ReceiptUploadPage::getUrl())
                    ->button(),
            ]);
    }
}
