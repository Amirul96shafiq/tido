@php
    use Filament\Support\Enums\Width;

    /** @var \App\Filament\Pages\CalendarPage $this */
    $monthLabel = $this->monthName;
    $monthLabelShort = $this->monthNameShort;
@endphp

<span class="tido-calendar-page-heading">
    Calendar (
    <x-filament::dropdown
        placement="bottom-start"
        shift
        :flip="false"
        max-height="min(40vh, 20rem)"
        :width="Width::ThreeExtraSmall"
        :wire:key="$this->getId().'.calendar.month-picker'"
        class="tido-calendar-heading-month-dropdown"
    >
        <x-slot name="trigger">
            <button
                type="button"
                class="tido-calendar-heading-month-trigger"
                aria-label="Change month, {{ $monthLabel }}"
            >
                <span class="hidden sm:inline">{{ $monthLabel }}</span>
                <span class="sm:hidden">{{ $monthLabelShort }}</span>
            </button>
        </x-slot>

        <div class="tido-calendar-heading-month-panel fi-fixed-positioning-context">
            {{ $this->getSchema('monthNavigationForm') }}
        </div>
    </x-filament::dropdown>
    )
</span>
