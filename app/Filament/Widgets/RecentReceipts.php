<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Invoice;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentReceipts extends BaseWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-receipts';

    public function table(Table $table): Table
    {
        $bounds = $this->getSelectedMonthBounds();

        return $table
            ->heading('Recent Receipts ('.$this->formatSelectedMonth('F Y').')')
            ->query(
                Invoice::query()
                    ->whereBetween('date_time', [$bounds['start'], $bounds['end']]),
            )
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->color(fn (Invoice $record): ?string => filled($record->image_path) ? 'primary' : null)
                    ->tooltip(fn (Invoice $record): ?string => filled($record->image_path) ? 'View file' : null)
                    ->url(
                        fn (Invoice $record): ?string => $record->fileUrl(),
                        shouldOpenInNewTab: true,
                    ),

                TextColumn::make('merchant_name')
                    ->label('Merchant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->myr()
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->badge()
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

                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class)
                    ->searchable(),
            ]);
    }
}
