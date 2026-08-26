@php
    /** @var \App\Filament\Pages\CalendarPage $this */
    $weeks = $this->weeks;
    $currentDayName = $this->currentDayKey();
    $isCurrentMonth = $this->isShowingCurrentMonth();
@endphp

<div
    class="tido-calendar"
    @calendar-close-popover.window="closePopover()"
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
        popoverStyle() {
            if (this.isMobile) {
                return { left: '50%', top: '50%', translate: '-50% -50%' };
            }

            const left = Math.max(20, Math.min(this.popoverPosition.x, window.innerWidth - 400));
            const top = Math.min(this.popoverPosition.y + 10, window.innerHeight - 440);
            const translateX = this.popoverPosition.x < 200 ? '0%' : '-50%';

            return { left: left + 'px', top: top + 'px', translate: translateX + ' 0' };
        },
        openPopover(eventData, position) {
            this.popoverEvents = eventData;
            this.popoverPosition = position;
            this.showEventPopover = true;
        },
        closePopover() {
            this.showEventPopover = false;
        },
        handleClickAway(event) {
            if (event.target.closest('.tido-calendar-event-chip, .tido-calendar__more-btn')) {
                return;
            }

            this.closePopover();
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

            <div
                class="tido-calendar__weeks"
                style="grid-template-rows: repeat({{ count($weeks) }}, minmax(0, 1fr));"
            >
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
        @click.away="handleClickAway($event)"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="tido-calendar__popover"
        :style="popoverStyle()"
        x-cloak
    >
        <div class="tido-calendar__popover-header">
            <h4 class="tido-calendar__popover-title" x-text="popoverEvents.date"></h4>
            <button
                type="button"
                @click="closePopover()"
                class="tido-calendar__popover-close"
                aria-label="Close"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="tido-calendar__popover-body custom-scrollbar">
            <template x-if="popoverEvents.events && popoverEvents.events.length > 0">
                <div class="space-y-3">
                    <template x-for="[moduleLabel, moduleEvents] in groupByModule(popoverEvents.events)" :key="moduleLabel">
                        <div class="space-y-2">
                            <p class="tido-calendar__popover-module" x-text="moduleLabel"></p>
                            <template x-for="(event, index) in moduleEvents" :key="`${moduleLabel}-${index}`">
                                <div
                                    class="tido-calendar__popover-card"
                                    :class="{ 'is-completed': event.completed }"
                                >
                                    <div class="tido-calendar__popover-card-top">
                                        <div class="tido-calendar__popover-badges">
                                            <span
                                                class="tido-calendar__popover-badge"
                                                :class="`is-${event.colorKey}`"
                                                x-show="event.status"
                                                x-text="event.status"
                                            ></span>
                                            <span
                                                class="tido-calendar__popover-badge is-scheduled"
                                                x-show="event.projected"
                                            >Scheduled</span>
                                        </div>
                                        <div
                                            class="tido-calendar__popover-actions"
                                            x-show="event.url || event.canSkip || event.canRevert"
                                        >
                                            <button
                                                type="button"
                                                x-show="event.canRevert"
                                                class="tido-calendar__popover-link"
                                                @click="$wire.mountAction('confirmRevertOccurrence', { occurrenceId: event.occurrenceId })"
                                            >
                                                Revert
                                            </button>
                                            <button
                                                type="button"
                                                x-show="event.canSkip"
                                                class="tido-calendar__popover-link is-muted"
                                                @click="$wire.mountAction('confirmSkipOccurrence', { occurrenceId: event.occurrenceId })"
                                            >
                                                Skip
                                            </button>
                                            <a
                                                x-show="event.url"
                                                :href="event.url"
                                                class="tido-calendar__popover-link"
                                            >
                                                View
                                            </a>
                                        </div>
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
