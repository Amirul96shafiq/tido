<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentReceipts extends BaseWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $bounds = $this->getSelectedMonthBounds();

        return $table
            ->heading('Recent Receipts ('.$this->formatSelectedMonth('F Y').')')
            ->query(
                Invoice::query()
                    ->whereBetween('date_time', [$bounds['start'], $bounds['end']])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('merchant_name')
                    ->label('Merchant'),

                TextColumn::make('date_time')
                    ->label('Date')
                    ->dateTime(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('MYR'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'parsed' => 'info',
                        'reviewed' => 'success',
                        'requires_manual_review' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}
