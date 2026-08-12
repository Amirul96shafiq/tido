@php
    use Filament\Support\Enums\Width;

    $activeFilterCount = $this->dashboardFiltersActiveCount();
@endphp

<x-filament::dropdown
    placement="bottom-end"
    shift
    :flip="false"
    max-height="min(40vh, 20rem)"
    :width="Width::ExtraSmall"
    :wire:key="$this->getId().'.dashboard.filters'"
    class="tido-dashboard-filters-dropdown"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            color="gray"
            icon="heroicon-m-funnel"
            label="Filters"
            tooltip="Filters"
            :badge="$activeFilterCount > 0 ? (string) $activeFilterCount : null"
            class="fi-dashboard-filters-trigger"
        />
    </x-slot>

    {{--
        fi-fixed-positioning-context: Filament Select uses position:fixed so option
        panels escape the scrollable Filters dropdown (same pattern as table filters).
    --}}
    <div class="tido-dashboard-filters-dropdown-panel fi-fixed-positioning-context">{!! $this->getSchema('filtersForm')->toHtml() !!}</div>
</x-filament::dropdown>
