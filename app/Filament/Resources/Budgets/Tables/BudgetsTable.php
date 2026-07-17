<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Tables;

use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('display_icon')
                    ->label('')
                    ->icon(fn (?string $state): ?string => $state)
                    ->color('gray'),

                TextColumn::make('display_title')
                    ->label('Title')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($inner) use ($search): void {
                            $inner->where('title', 'like', "%{$search}%")
                                ->orWhereHas('label', fn ($labelQuery) => $labelQuery->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->limit(24)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('label.name')
                    ->label('Label')
                    ->default('Overall (All Labels)')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('amount')
                    ->myr()
                    ->sortable(),

                TextColumn::make('period')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'daily' => 'info',
                        'weekly' => 'primary',
                        'monthly' => 'success',
                        'quarterly' => 'warning',
                        'yearly' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('quarter')
                    ->label('Quarter')
                    ->formatStateUsing(fn ($state) => $state ? 'Q'.$state : '-')
                    ->sortable(),

                TextColumn::make('year')
                    ->sortable(),

                TextColumn::make('alert_threshold')
                    ->label('Warn')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('critical_threshold')
                    ->label('Critical')
                    ->suffix('%')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('period')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ])
                    ->searchable(),

                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No budgets yet')
            ->emptyStateDescription('Create a budget to track spending against a limit.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Action::make('create')
                    ->label('New budget')
                    ->icon(Heroicon::Plus)
                    ->url(BudgetResource::getUrl('create'))
                    ->button(),
            ]);
    }
}
