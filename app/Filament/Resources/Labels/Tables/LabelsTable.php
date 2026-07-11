<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Tables;

use App\Enums\LabelType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LabelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (LabelType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable(),

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
                SelectFilter::make('type')
                    ->options(LabelType::options())
                    ->searchable(),

                TrashedFilter::make()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()->slideOver(),
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
            ]);
    }
}
