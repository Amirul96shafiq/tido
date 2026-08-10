<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Tables;

use App\Enums\HouseholdRole;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Support\RecordActionsGroup;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\User;
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

                IconColumn::make('display_icon')
                    ->label('Icon')
                    ->icon(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('familyMember.name')
                    ->label('Assigned to')
                    ->formatStateUsing(function (?string $state, Budget $record): string {
                        if ($record->family_member_id === null) {
                            return self::primaryUsername();
                        }

                        $member = $record->familyMember;

                        if (! $member instanceof FamilyMember) {
                            return 'Family member';
                        }

                        return filled($member->display_name)
                            ? (string) $member->display_name
                            : (string) $member->name;
                    })
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_shared')
                    ->label('Shared')
                    ->boolean()
                    ->toggleable(),

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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('year')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->sortable(),
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
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
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
