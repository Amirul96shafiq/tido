<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Concerns\RefreshesOnExpenseBroadcast;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringMatchService;
use App\Support\DueRecurringPreview;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
        $primaryUser = DueRecurringPreview::primaryUser();
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
            ->map(fn (RecurringOccurrence $occurrence): array => DueRecurringPreview::itemFromOccurrence(
                $occurrence,
                $isPrimary,
                $primaryUser,
            ))
            ->all();

        return [
            'canManageRecurrings' => true,
            'canReorderRecurrings' => $isPrimary,
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
}
