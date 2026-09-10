<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Support\PrimaryOnlyMutationAuthorization;
use App\Filament\Support\RecordActionsGroup;
use App\Models\PaymentMethod;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
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

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

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
                    ->formatStateUsing(fn (?string $state, PaymentMethod $record): ?string => filled($record->editedBy?->display_name)
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
                SelectFilter::make('is_system')
                    ->label('System Lock')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->searchable(),
                TrashedFilter::make()
                    ->searchable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (PaymentMethod $record): bool => HouseholdAccess::canManageHouseholdSettings(),
            )
            ->recordUrl(fn (PaymentMethod $record): ?string => PaymentMethodResource::canEdit($record)
                ? PaymentMethodResource::getUrl('edit', ['record' => $record])
                : null)
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage()),
                    PaymentMethodResource::duplicateAction(),
                    DeleteAction::make()
                        ->visible(fn ($record) => ! (bool) ($record?->is_system ?? false))
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage()),
                    RestoreAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage()),
                    ForceDeleteAction::make()
                        ->visible(fn ($record) => ! (bool) ($record?->is_system ?? false) && $record->trashed())
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PaymentMethodResource::duplicateBulkAction(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete')
                        ->action(function (Collection $records) {
                            $records->reject(fn ($record) => (bool) $record->is_system)->each->delete();
                        }),
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete')
                        ->action(function (Collection $records) {
                            $records->reject(fn ($record) => (bool) $record->is_system)->each->forceDelete();
                        }),
                    RestoreBulkAction::make()
                        ->authorizeIndividualRecords('restore'),
                ]),
            ])
            ->emptyStateHeading('No payment methods yet')
            ->emptyStateDescription('Create a payment method for receipts and analytics.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateActions([
                PrimaryOnlyMutationAuthorization::apply(
                    Action::make('create')
                        ->label('New payment method')
                        ->icon(Heroicon::Plus)
                        ->url(PaymentMethodResource::getUrl('create'))
                        ->button(),
                ),
            ]);
    }
}
