@php
    use Filament\Support\Enums\Width;

    /** @var list<array{key: string, label: string, module: string}> $filters */
    $filters = $filters ?? [];
@endphp

<x-filament::dropdown
    placement="bottom-end"
    shift
    :flip="false"
    max-height="min(40vh, 20rem)"
    :width="Width::ExtraSmall"
    :wire:key="$this->getId().'.calendar.filters'"
    class="tido-calendar-filters-dropdown"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            color="gray"
            icon="heroicon-m-funnel"
            label="Filter Events"
            tooltip="Filter Events"
            class="fi-calendar-filters-trigger"
        />
    </x-slot>

    <div class="tido-calendar-filters-dropdown-panel">
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
</x-filament::dropdown>
