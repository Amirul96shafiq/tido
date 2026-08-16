<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Tables;

use App\Enums\HouseholdRole;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Support\RecordActionsGroup;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\HouseholdAccess;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

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
                    ->badge()
                    ->placeholder('None')
                    ->default('Overall (All Labels)')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('display_icon')
                    ->label('Icon')
                    ->icon(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('assigned_to')
                    ->label('Assigned to')
                    ->state(function (Budget $record): string {
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

                TextColumn::make('amount')
                    ->myr()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('quarter')
                    ->label('Quarter')
                    ->formatStateUsing(fn ($state): string => 'Q'.$state)
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('year')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('alert_threshold')
                    ->label('Warn')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('critical_threshold')
                    ->label('Critical')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Budget $record): bool => ! HouseholdAccess::canMutateBudget($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, Budget $record): ?string => filled($record->editedBy?->display_name)
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
                SelectFilter::make('period')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ])
                    ->searchable(),

                SelectFilter::make('family_member_id')
                    ->label('Assigned to')
                    ->options(fn (): array => [
                        'primary' => self::primaryUsername(),
                        ...FamilyMember::query()
                            ->orderBy('name')
                            ->get(['id', 'name', 'display_name'])
                            ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                                (string) $familyMember->getKey() => filled($familyMember->display_name)
                                    ? (string) $familyMember->display_name
                                    : (string) $familyMember->name,
                            ])
                            ->all(),
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'primary') {
                            return $query->whereNull('family_member_id');
                        }

                        return $query->where('family_member_id', (int) $value);
                    })
                    ->searchable(),

                TernaryFilter::make('is_shared')
                    ->label('Shared with household')
                    ->placeholder('All')
                    ->trueLabel('Shared')
                    ->falseLabel('Personal'),

                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->searchable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Budget $record): bool => HouseholdAccess::canMutateBudget($record),
            )
            ->recordClasses(fn (Budget $record): array => HouseholdAccess::canMutateBudget($record)
                ? []
                : ['fi-ta-record-with-content-prefix', 'tido-ta-record-unsupported'])
            ->recordUrl(fn (Budget $record): ?string => BudgetResource::canEdit($record)
                ? BudgetResource::getUrl('edit', ['record' => $record])
                : null)
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Budget $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),
                    BudgetResource::duplicateAction(),
                    DeleteAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Budget $record): string => HouseholdAccess::assignedOwnerAuthorizationMessage($record->familyMember)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BudgetResource::duplicateBulkAction(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
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
                    ->authorize('create')
                    ->authorizationTooltip()
                    ->button(),
            ]);
    }

    protected static function primaryUsername(): string
    {
        $primaryUser = User::query()
            ->where(function ($query): void {
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
