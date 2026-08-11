<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringMatchService;
use App\Support\HouseholdAccess;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DueRecurrings extends Widget
{
    use HasDashboardSectionId;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 12,
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

    public function skipOccurrence(int $occurrenceId): void
    {
        $occurrence = $this->visibleOccurrenceQuery()
            ->whereKey($occurrenceId)
            ->first();

        if ($occurrence === null || ! $occurrence->isOpen()) {
            return;
        }

        app(RecurringMatchService::class)->skipOccurrence($occurrence);

        Notification::make()
            ->title('Occurrence skipped')
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
     *     totalAmount: string,
     *     totalCount: int
     * }
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $isPrimary = HouseholdAccess::isPrimary();
        $query = $this->visibleOccurrenceQuery($user instanceof User ? $user : null);

        $totalCount = (clone $query)->count();
        $totalAmount = MoneyDisplay::withPrefix((clone $query)->sum('expected_amount'));

        $items = (clone $query)
            ->with(['recurring.label'])
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
            ->map(function (RecurringOccurrence $occurrence) use ($isPrimary): array {
                $recurring = $occurrence->recurring;
                $label = $recurring?->label;
                $progress = $recurring?->goalProgressPercent();
                $progressAmount = $recurring?->goalProgressAmount();
                $goalTarget = $recurring?->goal_target_amount !== null
                    ? (float) $recurring->goal_target_amount
                    : null;

                return [
                    'id' => $occurrence->id,
                    'recurring_id' => $recurring?->id,
                    'can_reorder' => $isPrimary && $recurring !== null,
                    'edit_url' => $isPrimary && $recurring !== null
                        ? RecurringResource::getUrl('edit', ['record' => $recurring])
                        : null,
                    'title' => $recurring?->title ?? 'Recurring',
                    'icon' => $label?->icon ?: 'heroicon-o-arrow-path',
                    'color' => $label?->color ?: '#FFD07D',
                    'status' => $occurrence->status->value,
                    'statusLabel' => $occurrence->status->label(),
                    'dueOn' => $occurrence->due_on->format('d M Y'),
                    'amount' => $occurrence->expected_amount !== null
                        ? MoneyDisplay::withPrefix($occurrence->expected_amount)
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
            'contentHeight' => DashboardWidgetHeights::TREND_CHART,
            'items' => $items,
            'manageUrl' => RecurringResource::getUrl('index'),
            'totalAmount' => $totalAmount,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * @return list<int>
     */
    private function sortableRecurringIds(): array
    {
        return $this->visibleOccurrenceQuery()
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
            ->open()
            ->visibleTo($user)
            ->whereHas('recurring', fn ($query) => $query->active())
            ->whereIn('status', [
                RecurringOccurrenceStatus::Due,
                RecurringOccurrenceStatus::Overdue,
            ]);
    }
}
