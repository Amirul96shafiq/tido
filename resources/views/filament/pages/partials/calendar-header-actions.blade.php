<div class="tido-calendar-header-actions">
    <div class="tido-calendar__nav">
        <button
            type="button"
            wire:click="previousMonth"
            @click="$dispatch('calendar-close-popover')"
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
            @click="$dispatch('calendar-close-popover')"
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
            @click="$dispatch('calendar-close-popover')"
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

    @include('filament.pages.partials.calendar-filters-dropdown')
</div>
