<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RefreshesOnExpenseBroadcast;
use App\Models\User;
use App\Services\Calendar\CalendarEventAggregator;
use App\Services\RecurringOccurrenceGenerator;
use App\Support\Calendar\CalendarEvent;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class CalendarPage extends Page
{
    use PrependsHomeBreadcrumb;
    use RefreshesOnExpenseBroadcast;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'calendar';

    protected static ?string $title = 'Calendar';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public int $year;

    public int $month;

    /**
     * @var list<string>
     */
    public array $typeFilter = [];

    public function mount(CalendarEventAggregator $aggregator, RecurringOccurrenceGenerator $generator): void
    {
        $now = $this->viewerNow();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->typeFilter = array_column($aggregator->availableFilters(), 'key');

        $generator->run($now->copy()->startOfDay());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-calendar-page',
            'tido-calendar-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.pages.partials.calendar-content'),
            ]);
    }

    public function toggleTypeFilter(string $key): void
    {
        if (in_array($key, $this->typeFilter, true)) {
            $this->typeFilter = array_values(array_diff($this->typeFilter, [$key]));
        } else {
            $this->typeFilter[] = $key;
        }
    }

    public function clearTypeFilter(): void
    {
        $aggregator = app(CalendarEventAggregator::class);
        $this->typeFilter = array_column($aggregator->availableFilters(), 'key');
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $minYear = now()->year - 5;

        if ($date->year >= $minYear) {
            $this->year = (int) $date->year;
            $this->month = (int) $date->month;
        }
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $maxYear = now()->year + 5;

        if ($date->year <= $maxYear) {
            $this->year = (int) $date->year;
            $this->month = (int) $date->month;
        }
    }

    public function today(): void
    {
        $now = $this->viewerNow();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
    }

    public function goToMonth(int $month, int $year): void
    {
        $currentYear = now()->year;
        $minYear = $currentYear - 5;
        $maxYear = $currentYear + 5;

        $this->year = max($minYear, min($maxYear, $year));
        $this->month = max(1, min(12, $month));
    }

    public function getIsViewingTodayProperty(): bool
    {
        $now = $this->viewerNow();

        return $this->year === (int) $now->year && $this->month === (int) $now->month;
    }

    public function getPreviousMonthNameProperty(): string
    {
        return Carbon::create($this->year, $this->month, 1)->subMonth()->format('F');
    }

    public function getNextMonthNameProperty(): string
    {
        return Carbon::create($this->year, $this->month, 1)->addMonth()->format('F');
    }

    public function getMonthNameProperty(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /**
     * @return list<array{key: string, label: string, module: string}>
     */
    public function availableFilters(): array
    {
        return app(CalendarEventAggregator::class)->availableFilters();
    }

    public function currentDayKey(): string
    {
        return strtolower($this->viewerNow()->format('D'));
    }

    public function isShowingCurrentMonth(): bool
    {
        $now = $this->viewerNow();

        return $this->year === (int) $now->year && $this->month === (int) $now->month;
    }

    /**
     * @return list<list<array{
     *     date: Carbon,
     *     is_current_month: bool,
     *     is_today: bool,
     *     events: Collection<int, CalendarEvent>
     * }>>
     */
    public function getWeeksProperty(): array
    {
        $aggregator = app(CalendarEventAggregator::class);
        $startOfMonth = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();
        $calendarStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $grouped = $aggregator->eventsGroupedByDate(
            $calendarStart,
            $calendarEnd,
            $this->viewer(),
            $this->typeFilter,
        );

        $today = $this->viewerNow()->toDateString();
        $weeks = [];
        $currentDate = $calendarStart->copy();

        while ($currentDate <= $calendarEnd) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $currentDate->toDateString();

                $week[] = [
                    'date' => $currentDate->copy(),
                    'is_current_month' => $currentDate->month === $this->month,
                    'is_today' => $dateKey === $today,
                    'events' => $grouped->get($dateKey, collect()),
                ];

                $currentDate->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    public function formatPopoverDate(Carbon $date): string
    {
        return $date->format('l, j/n/y');
    }

    public function eventChipClasses(CalendarEvent $event): string
    {
        $base = 'tido-calendar-event-chip';

        if ((bool) ($event->meta['projected'] ?? false)) {
            return $base.' tido-calendar-event-chip--scheduled';
        }

        return $base.' tido-calendar-event-chip--'.$event->colorKey;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eventsForPopover(Collection $events): array
    {
        return $events
            ->map(static fn (CalendarEvent $event): array => $event->toPopoverArray())
            ->values()
            ->all();
    }

    #[On('recurring-occurrences-updated')]
    public function refreshCalendarOccurrences(): void {}

    protected function refreshFromExpenseBroadcast(): void
    {
        // Re-render calendar chips when expense matching completes an occurrence.
    }

    private function viewer(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function viewerNow(): Carbon
    {
        return now()->timezone($this->viewer()->preferredTimezone());
    }
}
