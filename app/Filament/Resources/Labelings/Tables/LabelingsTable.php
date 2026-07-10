<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Tables;

use App\Enums\LabelingType;
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

class LabelingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (LabelingType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('icon')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ColorColumn::make('color'),

                IconColumn::make('is_system')
                    ->boolean()
                    ->label('System Lock')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(LabelingType::options())
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
