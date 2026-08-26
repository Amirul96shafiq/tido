@php
    /** @var \App\Filament\Pages\CalendarPage $this */
    $weeks = $this->weeks;
    $filters = $this->availableFilters();
    $currentDayName = $this->currentDayKey();
    $isCurrentMonth = $this->isShowingCurrentMonth();
@endphp

<div
    class="tido-calendar"
    x-data="{
        showEventPopover: false,
        popoverEvents: [],
        popoverPosition: { x: 0, y: 0 },
        isMobile: false,
        init() {
            this.checkMobileSize();
            window.addEventListener('resize', () => this.checkMobileSize());
        },
        checkMobileSize() {
            this.isMobile = window.innerWidth < 640;
        },
        openPopover(eventData, position) {
            this.popoverEvents = eventData;
            this.popoverPosition = position;
            this.showEventPopover = true;
        },
        closePopover() {
            this.showEventPopover = false;
        },
        groupByModule(events) {
            const groups = {};
            (events || []).forEach((event) => {
                const key = event.moduleLabel || 'Other';
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(event);
            });
            return Object.entries(groups);
        },
    }"
>
    <div class="tido-calendar__header">
        <div class="tido-calendar__header-main">
            <button
                type="button"
                class="tido-calendar__month-label"
                wire:click="today"
                aria-label="Current month"
            >
                <span wire:loading.remove wire:target="previousMonth,nextMonth,goToMonth,today">
                    <span class="tido-calendar__month-label-compact">{{ $this->month }}/{{ substr((string) $this->year, -2) }}</span>
                    <span class="tido-calendar__month-label-full">{{ $this->monthName }}</span>
                </span>
                <span wire:loading wire:target="previousMonth,nextMonth,goToMonth,today">Loading…</span>
            </button>

            <div class="tido-calendar__header-actions">
                <div class="relative" x-data="{ filterOpen: false }" @click.outside="filterOpen = false">
                    <button
                        type="button"
                        class="tido-calendar__icon-btn"
                        @click="filterOpen = !filterOpen"
                        aria-label="Filter events"
                        x-tooltip="{
                            content: 'Filter Events',
                            theme: $store.theme,
                        }"
                    >
                        <x-heroicon-m-funnel class="h-4 w-4" />
                    </button>

                    <div
                        x-show="filterOpen"
                        x-transition
                        class="tido-calendar__filter-panel"
                        style="display: none;"
                    >
                        <div class="tido-calendar__filter-header">
                            <h3 class="tido-calendar__filter-title">Filter Events</h3>
                            <button
                                type="button"
                                wire:click="clearTypeFilter"
                                class="tido-calendar__filter-reset"
                            >
                                Show All
                            </button>
                        </div>

                        <div class="tido-calendar__filter-options">
                            @foreach ($filters as $filter)
                                <label class="tido-calendar__filter-option" wire:key="calendar-filter-{{ $filter['key'] }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $filter['key'] }}"
                                        wire:model.live="typeFilter"
                                        class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600"
                                    >
                                    <span>{{ $filter['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="tido-calendar__nav">
                    <button
                        type="button"
                        wire:click="previousMonth"
                        @click="closePopover()"
                        class="tido-calendar__nav-btn"
                        aria-label="Previous month"
                        x-tooltip="{
                            content: 'Go to {{ $this->previousMonthName }}',
                            theme: $store.theme,
                        }"
                    >
                        <x-heroicon-m-arrow-left wire:loading.remove wire:target="previousMonth" class="h-5 w-5" />
                        <x-filament::loading-indicator wire:loading wire:target="previousMonth" class="h-5 w-5" />
                    </button>

                    <button
                        type="button"
                        wire:click="today"
                        @click="closePopover()"
                        @disabled($this->isViewingToday)
                        class="tido-calendar__today-btn {{ $this->isViewingToday ? 'is-disabled' : '' }}"
                        x-tooltip="{
                            content: 'Jump to Today',
                            theme: $store.theme,
                        }"
                    >
                        <span wire:loading.remove wire:target="today">Today</span>
                        <x-filament::loading-indicator wire:loading wire:target="today" class="h-5 w-5" />
                    </button>

                    <button
                        type="button"
                        wire:click="nextMonth"
                        @click="closePopover()"
                        class="tido-calendar__nav-btn"
                        aria-label="Next month"
                        x-tooltip="{
                            content: 'Go to {{ $this->nextMonthName }}',
                            theme: $store.theme,
                        }"
                    >
                        <x-heroicon-m-arrow-right wire:loading.remove wire:target="nextMonth" class="h-5 w-5" />
                        <x-filament::loading-indicator wire:loading wire:target="nextMonth" class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="tido-calendar__grid-wrap">
        <div class="tido-calendar__grid-inner">
            <div wire:loading.flex wire:target="previousMonth,nextMonth,goToMonth,today,typeFilter" class="tido-calendar__loading">
                <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
                <p class="tido-calendar__loading-text">Loading calendar events…</p>
            </div>

            <div class="tido-calendar__day-headers">
                @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day)
                    <div class="tido-calendar__day-header">
                        <span class="{{ ($day === $currentDayName && $isCurrentMonth) ? 'is-today-column' : '' }}">
                            {{ ucfirst($day) }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="tido-calendar__weeks">
                @foreach ($weeks as $week)
                    <div class="tido-calendar__week" wire:key="calendar-week-{{ $loop->index }}-{{ $this->year }}-{{ $this->month }}">
                        @foreach ($week as $day)
                            @php
                                /** @var \Illuminate\Support\Collection<int, \App\Support\Calendar\CalendarEvent> $dayEvents */
                                $dayEvents = $day['events'];
                                $displayEvents = $dayEvents->take(2);
                                $remainingCount = max(0, $dayEvents->count() - $displayEvents->count());
                            @endphp

                            <div
                                class="tido-calendar__day {{ ! $day['is_current_month'] ? 'is-outside-month' : '' }} {{ $day['is_today'] ? 'is-today' : '' }}"
                                wire:key="calendar-day-{{ $day['date']->toDateString() }}"
                            >
                                <div class="tido-calendar__day-top">
                                    <span class="tido-calendar__day-number">{{ $day['date']->day }}</span>
                                    @if ($dayEvents->isNotEmpty())
                                        <span class="tido-calendar__day-count">
                                            {{ $dayEvents->count() }} {{ $dayEvents->count() === 1 ? 'event' : 'events' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="tido-calendar__day-events">
                                    @foreach ($displayEvents as $event)
                                        <button
                                            type="button"
                                            class="{{ $this->eventChipClasses($event) }}"
                                            @click="openPopover({{ \Illuminate\Support\Js::from([
                                                'date' => $this->formatPopoverDate($day['date']),
                                                'events' => $this->eventsForPopover($dayEvents),
                                            ]) }}, { x: $event.clientX, y: $event.clientY })"
                                        >
                                            <span class="tido-calendar-event-chip__dot"></span>
                                            <span class="tido-calendar-event-chip__label">{{ \Illuminate\Support\Str::limit($event->title, 28) }}</span>
                                        </button>
                                    @endforeach

                                    @if ($remainingCount > 0)
                                        <button
                                            type="button"
                                            class="tido-calendar__more-btn"
                                            @click="openPopover({{ \Illuminate\Support\Js::from([
                                                'date' => $this->formatPopoverDate($day['date']),
                                                'events' => $this->eventsForPopover($dayEvents),
                                            ]) }}, { x: $event.clientX, y: $event.clientY })"
                                        >
                                            +{{ $remainingCount }} more
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div
        x-show="showEventPopover"
        @click.away="closePopover()"
        x-transition
        class="tido-calendar__popover"
        :style="isMobile
            ? 'left: 50%; top: 50%; transform: translate(-50%, -50%);'
            : `left: ${Math.max(20, Math.min(popoverPosition.x, window.innerWidth - 340))}px; top: ${Math.min(popoverPosition.y + 10, window.innerHeight - 400)}px; transform: translateX(${popoverPosition.x < 180 ? '0%' : '-50%'});`"
        x-cloak
    >
        <div class="tido-calendar__popover-header">
            <h4 class="tido-calendar__popover-title" x-text="popoverEvents.date"></h4>
            <button type="button" @click="closePopover()" class="tido-calendar__popover-close" aria-label="Close">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="tido-calendar__popover-body">
            <template x-if="popoverEvents.events && popoverEvents.events.length > 0">
                <div class="space-y-4">
                    <template x-for="[moduleLabel, moduleEvents] in groupByModule(popoverEvents.events)" :key="moduleLabel">
                        <div class="space-y-2">
                            <p class="tido-calendar__popover-module" x-text="moduleLabel"></p>
                            <template x-for="(event, index) in moduleEvents" :key="`${moduleLabel}-${index}`">
                                <div class="tido-calendar__popover-card" :class="`is-${event.colorKey}`">
                                    <div class="tido-calendar__popover-card-top">
                                        <div class="tido-calendar__popover-badges">
                                            <span class="tido-calendar__popover-badge" x-show="event.status" x-text="event.status"></span>
                                            <span class="tido-calendar__popover-badge is-scheduled" x-show="event.projected">Scheduled</span>
                                        </div>
                                        <a
                                            x-show="event.url"
                                            :href="event.url"
                                            class="tido-calendar__popover-link"
                                        >
                                            View
                                        </a>
                                    </div>
                                    <p class="tido-calendar__popover-event-title" x-text="event.title"></p>
                                    <p class="tido-calendar__popover-event-subtitle" x-show="event.subtitle" x-text="event.subtitle"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
