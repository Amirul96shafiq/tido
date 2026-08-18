<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Concerns\RefreshesOnExpenseBroadcast;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Helpers\MoneyDisplay;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class RecurringMonthSnapshot extends Widget
{
    use HasDashboardSectionId;
    use RefreshesOnExpenseBroadcast;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    protected string $view = 'filament.widgets.recurring-month-snapshot';

    public static function dashboardSectionId(): string
    {
        return 'recurring-month-snapshot';
    }

    public static function headingLabel(): string
    {
        return 'Bills · '.now()->format('F Y');
    }

    #[On('recurring-occurrences-updated')]
    public function refreshSnapshot(): void {}

    public static function dueCountdownLabel(?CarbonInterface $dueOn): ?string
    {
        if ($dueOn === null) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($dueOn->copy()->startOfDay());

        if ($days === 0) {
            return 'Due today';
        }

        if ($days > 0) {
            return $days.' '.Str::plural('day', $days).' left';
        }

        $overdueDays = abs($days);

        return $overdueDays.' '.Str::plural('day', $overdueDays).' overdue';
    }

    /**
     * @return array{
     *     completedCount: int,
     *     contentHeight: string,
     *     dueCount: int,
     *     heading: string,
     *     isEmpty: bool,
     *     isNextDueOverdue: bool,
     *     nextDueDetail: ?string,
     *     nextDueLabel: ?string,
     *     overdueCount: int,
     *     paidAmount: string,
     *     remainingAmount: string,
     *     ringPercent: float,
     *     ringTotal: int,
     *     totalAmount: string,
     *     upcomingCount: int
     * }
     */
    protected function getViewData(): array
    {
        $query = $this->visibleOccurrenceQuery();

        $openQuery = (clone $query)->whereIn('status', [
            RecurringOccurrenceStatus::Due,
            RecurringOccurrenceStatus::Overdue,
            RecurringOccurrenceStatus::Upcoming,
        ]);
        $completedQuery = (clone $query)->where('status', RecurringOccurrenceStatus::Completed);

        $openCount = (clone $openQuery)->count();
        $completedCount = (clone $completedQuery)->count();
        $ringTotal = $completedCount + $openCount;
        $ringPercent = $ringTotal > 0
            ? round(($completedCount / $ringTotal) * 100, 2)
            : 0.0;

        $remainingAmountValue = (float) (clone $openQuery)
            ->with('recurring')
            ->get()
            ->sum(fn (RecurringOccurrence $occurrence): float => (float) ($occurrence->resolvedExpectedAmount() ?? 0));
        $paidAmountValue = (float) (clone $completedQuery)->sum(DB::raw('COALESCE(actual_amount, expected_amount)'));

        $overdueCount = (clone $query)->where('status', RecurringOccurrenceStatus::Overdue)->count();
        $dueCount = (clone $query)->where('status', RecurringOccurrenceStatus::Due)->count();
        $upcomingCount = (clone $query)->where('status', RecurringOccurrenceStatus::Upcoming)->count();

        $nextDue = (clone $openQuery)
            ->with('recurring')
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                RecurringOccurrenceStatus::Overdue->value,
                RecurringOccurrenceStatus::Due->value,
            ])
            ->orderBy('due_on')
            ->orderBy('id')
            ->first();

        $nextDueStatus = $nextDue?->status;
        $nextDueLabel = match ($nextDueStatus) {
            RecurringOccurrenceStatus::Overdue => 'Overdue:',
            RecurringOccurrenceStatus::Due, RecurringOccurrenceStatus::Upcoming => 'Upcoming Next Due:',
            default => null,
        };

        $nextDueDetail = null;

        if (
            $nextDueLabel !== null
            && filled($nextDue?->recurring?->title)
            && $nextDue?->due_on instanceof CarbonInterface
        ) {
            $nextDueDetail = implode(' · ', array_filter([
                $nextDue->recurring->title,
                $nextDue->due_on->format('d M'),
                self::dueCountdownLabel($nextDue->due_on),
            ]));
        }

        return [
            'completedCount' => $completedCount,
            'contentHeight' => DashboardWidgetHeights::STANDARD_CHART,
            'dueCount' => $dueCount,
            'heading' => self::headingLabel(),
            'isEmpty' => ! (clone $query)->exists(),
            'isNextDueOverdue' => $nextDueStatus === RecurringOccurrenceStatus::Overdue,
            'nextDueDetail' => $nextDueDetail,
            'nextDueLabel' => $nextDueLabel,
            'overdueCount' => $overdueCount,
            'paidAmount' => MoneyDisplay::withPrefix($paidAmountValue),
            'remainingAmount' => MoneyDisplay::withPrefix($remainingAmountValue),
            'ringPercent' => $ringPercent,
            'ringTotal' => $ringTotal,
            'totalAmount' => MoneyDisplay::withPrefix($remainingAmountValue + $paidAmountValue),
            'upcomingCount' => $upcomingCount,
        ];
    }

    /**
     * @return Builder<RecurringOccurrence>
     */
    private function visibleOccurrenceQuery(): Builder
    {
        $user = Auth::user() instanceof User ? Auth::user() : null;

        return RecurringOccurrence::query()
            ->visibleTo($user)
            ->forDashboardMonth();
    }
}
