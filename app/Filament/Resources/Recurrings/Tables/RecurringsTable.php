<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Tables;

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Support\RecordActionsGroup;
use App\Helpers\MoneyDisplay;
use App\Models\Recurring;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Xplodman\CountUp\Facades\CountUpStat;
use Illuminate\Database\Eloquent\Builder;

class RecurringsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(28)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (RecurringType|string $state): string => $state instanceof RecurringType
                        ? $state->label()
                        : RecurringType::from($state)->label())
                    ->sortable(),

                TextColumn::make('label.name')
                    ->label('Label')
                    ->badge()
                    ->placeholder('None')
                    ->toggleable(),

                TextColumn::make('assigned_to')
                    ->label('Assigned to')
                    ->state(function (Recurring $record): string {
                        $member = $record->familyMember;

                        if ($member === null) {
                            return self::primaryUsername();
                        }

                        return filled($member->display_name)
                            ? (string) $member->display_name
                            : (string) $member->name;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('is_shared')
                    ->label('Shared')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('expected_amount')
                    ->formatStateUsing(fn (mixed $state): \Illuminate\Contracts\Support\Htmlable|string => $state === null
                        ? 'Variable'
                        : CountUpStat::animate(MoneyDisplay::withPrefix($state)))
                    ->placeholder('Variable')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('cadence')
                    ->label('Cadence')
                    ->badge()
                    ->state(function (Recurring $record): string {
                        if ($record->frequency === RecurringFrequency::Once) {
                            return 'Once';
                        }

                        $months = (int) ($record->interval_months ?? 1);

                        return match ($months) {
                            1 => 'Monthly',
                            3 => 'Quarterly',
                            6 => 'Every 6 months',
                            12 => 'Yearly',
                            default => "Every {$months} months",
                        };
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('next_due_on')
                    ->label('Next due')
                    ->state(fn (Recurring $record): ?string => $record->nextOpenDueOn()?->toDateString())
                    ->date()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = $direction === 'desc' ? 'desc' : 'asc';

                        return $query->orderByRaw(
                            'coalesce((select min(due_on) from recurring_occurrences where recurring_occurrences.recurring_id = recurrings.id and status in (?, ?, ?)), recurrings.next_due_on) '.$direction,
                            [
                                RecurringOccurrenceStatus::Upcoming->value,
                                RecurringOccurrenceStatus::Due->value,
                                RecurringOccurrenceStatus::Overdue->value,
                            ],
                        );
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Recurring $record): bool => ! HouseholdAccess::canMutateRecurring($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, Recurring $record): ?string => filled($record->editedBy?->display_name)
                        ? (string) $record->editedBy->display_name
                        : $state)
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('updated_at')
                    ->label('Edited At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(RecurringType::options())
                    ->searchable(),
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->searchable(),
                TernaryFilter::make('is_shared')
                    ->label('Shared')
                    ->searchable(),
                TrashedFilter::make()
                    ->searchable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Recurring $record): bool => HouseholdAccess::canMutateRecurring($record),
            )
            ->recordClasses(fn (Recurring $record): array => HouseholdAccess::canMutateRecurring($record)
                ? []
                : ['fi-ta-record-with-content-prefix', 'tido-ta-record-unsupported'])
            ->recordUrl(fn (Recurring $record): ?string => RecurringResource::canEdit($record)
                ? RecurringResource::getUrl('edit', ['record' => $record])
                : null)
            ->recordActions([
                ViewAction::make()->slideOver(),
                RecordActionsGroup::make([
                    EditAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Recurring $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),

                    RecurringResource::duplicateAction(),

                    DeleteAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Recurring $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),

                    RestoreAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Recurring $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),

                    ForceDeleteAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Recurring $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RecurringResource::duplicateBulkAction(),

                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),

                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete'),

                    RestoreBulkAction::make()
                        ->authorizeIndividualRecords('restore'),
                ]),
            ]);
    }

    private static function primaryUsername(): string
    {
        $primaryUser = User::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name', 'display_name']);

        if (! $primaryUser instanceof User) {
            return 'Primary';
        }

        return filled($primaryUser->display_name)
            ? (string) $primaryUser->display_name
            : (string) $primaryUser->name;
    }
}
