<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchant_name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('date_time')
                    ->dateTime()
                    ->sortable(),
                
                TextColumn::make('total_amount')
                    ->money('MYR')
                    ->sortable(),
                
                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'whatsapp' => 'success',
                        'google_drive' => 'warning',
                        default => 'gray',
                    }),
                
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
                
                TrashedFilter::make()
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
