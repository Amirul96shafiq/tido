<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RefreshesOnExpenseBroadcast;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\Calendar\CalendarEventAggregator;
use App\Services\RecurringMatchService;
use App\Services\RecurringOccurrenceGenerator;
use App\Support\Calendar\CalendarEvent;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
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

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    public int $year;

    public int $month;

    /**
     * @var array<string, bool>
     */
    public array $typeFilter = [];

    public function mount(RecurringOccurrenceGenerator $generator): void
    {
        $now = $this->viewerNow();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->typeFilter = $this->defaultTypeFilter();

        $generator->run($now->copy()->startOfDay());
    }

    public function getHeading(): string
    {
        return 'Calendar ('.$this->monthName.')';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendarHeaderActions')
                ->label('Calendar actions')
                ->view('filament.pages.partials.calendar-header-actions'),
        ];
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

    public function filtersForm(Schema $schema): Schema
    {
        $checkboxes = [];

        foreach ($this->availableFilters() as $filter) {
            $checkboxes[] = Checkbox::make($filter['key'])
                ->label($filter['label']);
        }

        return $schema
            ->columns(1)
            ->live()
            ->statePath('typeFilter')
            ->components($checkboxes);
    }

    public function resetTypeFilter(): void
    {
        $this->typeFilter = $this->defaultTypeFilter();
    }

    public function typeFilterActiveCount(): int
    {
        $count = 0;

        foreach (array_keys($this->defaultTypeFilter()) as $key) {
            if (! $this->typeFilterIsEnabled($key)) {
                $count++;
            }
        }

        return $count;
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
            $this->enabledTypeFilterKeys(),
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
        $completed = (bool) ($event->meta['completed'] ?? false);

        if ((bool) ($event->meta['projected'] ?? false)) {
            return $base.' tido-calendar-event-chip--scheduled';
        }

        $classes = $base.' tido-calendar-event-chip--'.$event->colorKey;

        if ($completed) {
            $classes .= ' tido-calendar-event-chip--completed';
        }

        return $classes;
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
        $this->dispatch('calendar-close-popover');

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
        $this->dispatch('calendar-close-popover');

        Notification::make()
            ->title('Occurrence restored')
            ->success()
            ->send();
    }

    #[On('recurring-occurrences-updated')]
    public function refreshCalendarOccurrences(): void {}

    protected function refreshFromExpenseBroadcast(): void
    {
        // Re-render calendar chips when expense matching completes an occurrence.
    }

    /**
     * @return array<string, bool>
     */
    private function defaultTypeFilter(): array
    {
        return array_fill_keys(
            array_column($this->availableFilters(), 'key'),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function enabledTypeFilterKeys(): array
    {
        $keys = [];

        foreach ($this->availableFilters() as $filter) {
            if ($this->typeFilterIsEnabled($filter['key'])) {
                $keys[] = $filter['key'];
            }
        }

        return $keys;
    }

    private function typeFilterIsEnabled(string $key): bool
    {
        return filter_var($this->typeFilter[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
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

    /**
     * @return Builder<RecurringOccurrence>
     */
    private function visibleOccurrenceQuery(): Builder
    {
        return RecurringOccurrence::query()
            ->visibleTo($this->viewer());
    }
}
