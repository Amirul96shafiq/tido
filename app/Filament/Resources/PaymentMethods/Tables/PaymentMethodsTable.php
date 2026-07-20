<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('slug')
                    ->searchable(),

                TextColumn::make('aliases')
                    ->label('Aliases')
                    ->state(function ($record): string {
                        $aliases = is_array($record?->aliases) ? $record->aliases : [];
                        $aliases = array_values(array_filter(
                            $aliases,
                            fn (mixed $alias): bool => is_string($alias) && $alias !== '',
                        ));

                        if ($aliases === []) {
                            return '—';
                        }

                        $formatted = $aliases[0];
                        $remaining = count($aliases) - 1;
                        if ($remaining > 0) {
                            $formatted .= ' + '.$remaining.' more';
                        }

                        return $formatted;
                    })
                    ->tooltip(function ($record): ?string {
                        $aliases = is_array($record?->aliases) ? $record->aliases : [];
                        $aliases = array_values(array_filter(
                            $aliases,
                            fn (mixed $alias): bool => is_string($alias) && $alias !== '',
                        ));

                        if (count($aliases) <= 1) {
                            return null;
                        }

                        return implode(', ', $aliases);
                    })
                    ->toggleable(),

                IconColumn::make('icon')
                    ->icon(fn (?string $state): ?string => $state)
                    ->toggleable(),

                ColorColumn::make('color'),

                IconColumn::make('is_system')
                    ->boolean()
                    ->label('System Lock')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => ! (bool) ($record?->is_system ?? false)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $records->reject(fn ($record) => (bool) $record->is_system)->each->delete();
                        }),
                    ForceDeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $records->reject(fn ($record) => (bool) $record->is_system)->each->forceDelete();
                        }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No payment methods yet')
            ->emptyStateDescription('Create a payment method for receipts and analytics.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateActions([
                Action::make('create')
                    ->label('New payment method')
                    ->icon(Heroicon::Plus)
                    ->url(PaymentMethodResource::getUrl('create'))
                    ->button(),
            ]);
    }
}
