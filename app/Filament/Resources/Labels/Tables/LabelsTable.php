<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Tables;

use App\Enums\LabelType;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Support\RecordActionsGroup;
use App\Models\Label;
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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (LabelType $state): string => $state->label())
                    ->sortable(),

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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('icon')
                    ->icon(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: false),

                ColorColumn::make('color')
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('is_system')
                    ->boolean()
                    ->label('System Lock')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, Label $record): ?string => filled($record->editedBy?->display_name)
                        ? (string) $record->editedBy->display_name
                        : $state)
                    ->placeholder('System')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Edited At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(LabelType::options())
                    ->searchable(),

                TrashedFilter::make()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make(),
                    LabelResource::duplicateAction(),
                    DeleteAction::make()
                        ->visible(fn ($record) => ! (bool) ($record?->is_system ?? false)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    LabelResource::duplicateBulkAction(),
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
            ->emptyStateHeading('No labels yet')
            ->emptyStateDescription('Create a label to categorize expenses.')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Action::make('create')
                    ->label('New label')
                    ->icon(Heroicon::Plus)
                    ->url(LabelResource::getUrl('create'))
                    ->button(),
            ]);
    }
}
