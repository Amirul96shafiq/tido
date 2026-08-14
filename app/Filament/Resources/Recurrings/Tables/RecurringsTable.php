<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Tables;

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Filament\Support\RecordActionsGroup;
use App\Models\Recurring;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
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
                    ->placeholder('—')
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
                    ->myr()
                    ->placeholder('Variable')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('cadence')
                    ->label('Cadence')
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
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, Recurring $record): ?string => filled($record->editedBy?->display_name)
                        ? (string) $record->editedBy->display_name
                        : $state)
                    ->placeholder('—')
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
            ])
            ->recordActions([
                ViewAction::make()->slideOver(),
                RecordActionsGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
