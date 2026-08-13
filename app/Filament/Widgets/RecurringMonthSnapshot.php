<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Helpers\MoneyDisplay;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecurringMonthSnapshot extends Widget
{
    use HasDashboardSectionId;

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
        return now()->format('F Y')."'s Bills";
    }

    /**
     * @return array{
     *     completedCount: int,
     *     contentHeight: string,
     *     dueCount: int,
     *     heading: string,
     *     isEmpty: bool,
     *     nextDueOn: ?string,
     *     nextDueTitle: ?string,
     *     openAmount: string,
     *     overdueCount: int,
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

        $openAmountValue = (float) (clone $openQuery)->sum('expected_amount');
        $completedAmountValue = (float) (clone $completedQuery)->sum(DB::raw('COALESCE(actual_amount, expected_amount)'));

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

        return [
            'completedCount' => $completedCount,
            'contentHeight' => DashboardWidgetHeights::STANDARD_CHART,
            'dueCount' => $dueCount,
            'heading' => self::headingLabel(),
            'isEmpty' => ! (clone $query)->exists(),
            'nextDueOn' => $nextDue?->due_on?->format('d M'),
            'nextDueTitle' => $nextDue?->recurring?->title,
            'openAmount' => MoneyDisplay::withPrefix($openAmountValue),
            'overdueCount' => $overdueCount,
            'ringPercent' => $ringPercent,
            'ringTotal' => $ringTotal,
            'totalAmount' => MoneyDisplay::withPrefix($openAmountValue + $completedAmountValue),
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
