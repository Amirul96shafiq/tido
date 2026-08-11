<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringMatchService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
     *     items: list<array<string, mixed>>,
     *     manageUrl: string,
     *     totalAmount: string,
     *     totalCount: int
     * }
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $query = $this->visibleOccurrenceQuery($user instanceof User ? $user : null);

        $totalCount = (clone $query)->count();
        $totalAmount = MoneyDisplay::withPrefix((clone $query)->sum('expected_amount'));

        $items = (clone $query)
            ->with(['recurring.label'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'due' THEN 1 ELSE 2 END")
            ->orderBy('due_on')
            ->limit(12)
            ->get()
            ->map(function (RecurringOccurrence $occurrence): array {
                $recurring = $occurrence->recurring;
                $progress = $recurring?->goalProgressPercent();

                return [
                    'id' => $occurrence->id,
                    'title' => $recurring?->title ?? 'Recurring',
                    'status' => $occurrence->status->value,
                    'statusLabel' => $occurrence->status->label(),
                    'dueOn' => $occurrence->due_on->format('d M Y'),
                    'amount' => $occurrence->expected_amount !== null
                        ? MoneyDisplay::withPrefix($occurrence->expected_amount)
                        : 'Variable',
                    'type' => $recurring?->type->label() ?? '',
                    'progress' => $progress,
                    'editUrl' => $recurring !== null
                        ? RecurringResource::getUrl('edit', ['record' => $recurring])
                        : null,
                ];
            })
            ->all();

        return [
            'items' => $items,
            'manageUrl' => RecurringResource::getUrl('index'),
            'totalAmount' => $totalAmount,
            'totalCount' => $totalCount,
        ];
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
