<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Concerns\RefreshesOnExpenseBroadcast;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringMatchService;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DueRecurrings extends Widget implements HasActions, HasSchemas
{
    use HasDashboardSectionId;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesOnExpenseBroadcast;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 8,
    ];

    protected string $view = 'filament.widgets.due-recurrings';

    public static function dashboardSectionId(): string
    {
        return 'due-recurrings';
    }

    public function reorderRecurrings(int|string $id, int $position): void
    {
        if (! HouseholdAccess::isPrimary()) {
            return;
        }

        $recurringId = (int) $id;

        $orderedIds = $this->sortableRecurringIds();
        $fromIndex = array_search($recurringId, $orderedIds, true);

        if ($fromIndex === false) {
            return;
        }

        $position = max(0, min($position, count($orderedIds) - 1));

        array_splice($orderedIds, $fromIndex, 1);
        array_splice($orderedIds, $position, 0, [$recurringId]);

        $sortOrders = Recurring::query()
            ->whereKey($orderedIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('sort_order')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        DB::transaction(function () use ($orderedIds, $sortOrders): void {
            foreach ($orderedIds as $index => $orderedId) {
                Recurring::query()
                    ->whereKey($orderedId)
                    ->update(['sort_order' => $sortOrders[$index] ?? $index]);
            }
        });
    }

    public function confirmSkipOccurrence(): Action
    {
        return Action::make('confirmSkipOccurrence')
            ->requiresConfirmation()
            ->modalHeading('Skip occurrence?')
            ->modalDescription('This occurrence will be marked as skipped.')
            ->modalSubmitActionLabel('Skip')
            ->action(function (array $arguments): void {
                $this->skipOccurrence((int) ($arguments['occurrenceId'] ?? 0));
            });
    }

    public function confirmRevertOccurrence(): Action
    {
        return Action::make('confirmRevertOccurrence')
            ->requiresConfirmation()
            ->modalHeading('Revert skipped occurrence?')
            ->modalDescription('This occurrence will return to the due list.')
            ->modalSubmitActionLabel('Revert back')
            ->action(function (array $arguments): void {
                $this->revertOccurrence((int) ($arguments['occurrenceId'] ?? 0));
            });
    }

    public function skipOccurrence(int $occurrenceId): void
    {
        $occurrence = $this->visibleOccurrenceQuery()
            ->whereKey($occurrenceId)
            ->first();

        if ($occurrence === null || ! $occurrence->isOpen()) {
            return;
        }

        app(RecurringMatchService::class)->skipOccurrence($occurrence);

        $this->dispatch('recurring-occurrences-updated');

        Notification::make()
            ->title('Occurrence skipped')
            ->success()
            ->send();
    }

    public function revertOccurrence(int $occurrenceId): void
    {
        $occurrence = $this->visibleOccurrenceQuery()
            ->whereKey($occurrenceId)
            ->first();

        if ($occurrence === null || $occurrence->status !== RecurringOccurrenceStatus::Skipped) {
            return;
        }

        app(RecurringMatchService::class)->revertOccurrence($occurrence);

        $this->dispatch('recurring-occurrences-updated');

        Notification::make()
            ->title('Occurrence restored')
            ->success()
            ->send();
    }

    public function markPaid(int $occurrenceId, int $expenseId): void
    {
        $occurrence = $this->visibleOccurrenceQuery()
            ->whereKey($occurrenceId)
            ->first();

        $expense = Expense::query()->find($expenseId);

        if ($occurrence === null || $expense === null || ! $occurrence->isOpen()) {
            return;
        }

        app(RecurringMatchService::class)->completeOccurrence($occurrence, $expense);

        $this->dispatch('recurring-occurrences-updated');

        Notification::make()
            ->title('Occurrence marked paid')
            ->success()
            ->send();
    }

    /**
     * @return array{
     *     canManageRecurrings: bool,
     *     contentHeight: string,
     *     items: list<array<string, mixed>>,
     *     manageUrl: string,
     *     titleIndicator: 'alert'|'calm',
     *     totalCount: int
     * }
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $isPrimary = HouseholdAccess::isPrimary();
        $primaryUser = $this->resolvePrimaryUser();
        $query = $this->visibleOccurrenceQuery($user instanceof User ? $user : null);

        $actionableQuery = (clone $query)->whereIn('status', [
            RecurringOccurrenceStatus::Due,
            RecurringOccurrenceStatus::Overdue,
        ]);
        $openQuery = (clone $query)->whereIn('status', [
            RecurringOccurrenceStatus::Due,
            RecurringOccurrenceStatus::Overdue,
            RecurringOccurrenceStatus::Upcoming,
        ]);

        $totalCount = (clone $openQuery)->count();
        $titleIndicator = (clone $actionableQuery)->count() > 0 ? 'alert' : 'calm';

        $items = (clone $query)
            ->with(['recurring.label', 'recurring.familyMember', 'expense'])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', [
                RecurringOccurrenceStatus::Completed->value,
                RecurringOccurrenceStatus::Skipped->value,
            ])
            ->orderBy(
                Recurring::query()
                    ->select('sort_order')
                    ->whereColumn('recurrings.id', 'recurring_occurrences.recurring_id')
                    ->limit(1),
            )
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(function (RecurringOccurrence $occurrence) use ($isPrimary, $primaryUser): array {
                $recurring = $occurrence->recurring;
                $label = $recurring?->label;
                $owner = $recurring !== null
                    ? $this->recurringOwnerProfile($recurring, $primaryUser)
                    : ['avatar_url' => app(UiAvatarsProvider::class)->get(new User(['name' => 'Primary'])), 'name' => 'Primary'];
                $progress = $recurring?->goalProgressPercent();
                $progressAmount = $recurring?->goalProgressAmount();
                $goalTarget = $recurring?->goal_target_amount !== null
                    ? (float) $recurring->goal_target_amount
                    : null;
                $isCompleted = $occurrence->status === RecurringOccurrenceStatus::Completed;
                $isSkipped = $occurrence->status === RecurringOccurrenceStatus::Skipped;
                $displayAmount = $isCompleted
                    ? ($occurrence->actual_amount ?? $occurrence->expected_amount)
                    : $occurrence->expected_amount;
                $completedAt = $occurrence->expense?->date_time ?? $occurrence->updated_at;

                return [
                    'id' => $occurrence->id,
                    'recurring_id' => $recurring?->id,
                    'can_reorder' => $isPrimary && $recurring !== null && ! $isCompleted && ! $isSkipped,
                    'is_completed' => $isCompleted,
                    'is_skipped' => $isSkipped,
                    'edit_url' => $isPrimary && $recurring !== null
                        ? RecurringResource::getUrl('edit', ['record' => $recurring])
                        : null,
                    'title' => $recurring?->title ?? 'Recurring',
                    'owner_avatar_url' => $owner['avatar_url'],
                    'owner_name' => $owner['name'],
                    'icon' => $label?->icon ?: 'heroicon-o-arrow-path',
                    'color' => $label?->color ?: '#FFD07D',
                    'status' => $occurrence->status->value,
                    'statusLabel' => $occurrence->status->label(),
                    'dueOn' => $occurrence->due_on->format('d M Y'),
                    'completedAt' => $completedAt?->format('d M Y H:i'),
                    'amount' => $displayAmount !== null
                        ? MoneyDisplay::withPrefix($displayAmount)
                        : 'Variable',
                    'type' => $recurring?->type->label() ?? '',
                    'cadence' => $this->cadenceLabel($recurring),
                    'is_shared' => (bool) ($recurring?->is_shared ?? false),
                    'progress' => $progress,
                    'progressAmount' => $progressAmount,
                    'goalTarget' => $goalTarget,
                ];
            })
            ->all();

        return [
            'canManageRecurrings' => $isPrimary,
            'contentHeight' => DashboardWidgetHeights::STANDARD_CHART,
            'items' => $items,
            'manageUrl' => RecurringResource::getUrl('index'),
            'titleIndicator' => $titleIndicator,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * @return list<int>
     */
    private function sortableRecurringIds(): array
    {
        return $this->visibleOccurrenceQuery()
            ->whereIn('status', [
                RecurringOccurrenceStatus::Due,
                RecurringOccurrenceStatus::Overdue,
                RecurringOccurrenceStatus::Upcoming,
            ])
            ->orderBy(
                Recurring::query()
                    ->select('sort_order')
                    ->whereColumn('recurrings.id', 'recurring_occurrences.recurring_id')
                    ->limit(1),
            )
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(12)
            ->pluck('recurring_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function cadenceLabel(?Recurring $recurring): string
    {
        if ($recurring === null) {
            return '';
        }

        if ($recurring->frequency === RecurringFrequency::Once) {
            return 'Once';
        }

        $months = (int) ($recurring->interval_months ?? 1);

        return match ($months) {
            1 => 'Monthly',
            3 => 'Quarterly',
            6 => 'Every 6 months',
            12 => 'Yearly',
            default => "Every {$months} months",
        };
    }

    /**
     * @return Builder<RecurringOccurrence>
     */
    private function visibleOccurrenceQuery(?User $user = null): Builder
    {
        $user ??= Auth::user() instanceof User ? Auth::user() : null;

        return RecurringOccurrence::query()
            ->visibleTo($user)
            ->forDashboardMonth();
    }

    private function resolvePrimaryUser(): ?User
    {
        return User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{avatar_url: string, name: string}
     */
    private function recurringOwnerProfile(Recurring $recurring, ?User $primaryUser): array
    {
        $familyMember = $recurring->familyMember;

        if ($familyMember instanceof FamilyMember) {
            return [
                'avatar_url' => $this->avatarDisplayUrl($familyMember),
                'name' => filled($familyMember->display_name)
                    ? (string) $familyMember->display_name
                    : (string) $familyMember->name,
            ];
        }

        if ($primaryUser instanceof User) {
            return [
                'avatar_url' => $this->avatarDisplayUrl($primaryUser),
                'name' => filled($primaryUser->display_name)
                    ? (string) $primaryUser->display_name
                    : (string) $primaryUser->name,
            ];
        }

        return [
            'avatar_url' => app(UiAvatarsProvider::class)->get(new User(['name' => 'Primary'])),
            'name' => 'Primary',
        ];
    }

    private function avatarDisplayUrl(User|FamilyMember $record): string
    {
        $url = $record->getFilamentAvatarUrl();

        if ($url !== null) {
            return $url;
        }

        return app(UiAvatarsProvider::class)->get($record);
    }
}
