@php
    use Filament\Support\Enums\Width;

    $activeFilterCount = $this->dashboardFiltersActiveCount();
@endphp

<x-filament::dropdown
    placement="bottom-start"
    shift
    :flip="false"
    :width="Width::ExtraSmall"
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

    <div class="tido-dashboard-filters-dropdown-panel">
        {!! $this->getSchema('filtersForm')->toHtml() !!}
    </div>
</x-filament::dropdown>
