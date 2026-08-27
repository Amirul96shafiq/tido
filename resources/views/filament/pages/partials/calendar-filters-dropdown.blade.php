@php
    use Filament\Support\Enums\Width;

    $filterLabel = __('filament-tables::table.actions.filter.label');
    $resetLabel = __('filament-tables::table.filters.actions.reset.label');
    $activeFilterCount = $this->typeFilterActiveCount();
@endphp

<x-filament::dropdown
    placement="bottom-end"
    shift
    :flip="false"
    max-height="min(40vh, 20rem)"
    :width="Width::ExtraSmall"
    :wire:key="$this->getId().'.calendar.filters'"
    class="fi-ta-filters-dropdown"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            color="gray"
            icon="heroicon-m-funnel"
            :label="$filterLabel"
            :tooltip="$filterLabel"
            :badge="$activeFilterCount > 0 ? (string) $activeFilterCount : null"
            class="fi-calendar-filters-trigger fi-force-enabled"
        />
    </x-slot>

    <div class="fi-ta-filters fi-fixed-positioning-context">
        <div class="fi-ta-filters-body">
            {{ $this->getSchema('filtersForm') }}
        </div>

        <div class="fi-ta-filters-actions-ctn">
            <x-filament::button
                color="primary"
                icon="heroicon-o-arrow-path"
                label-sr-only
                :tooltip="$resetLabel"
                tag="button"
                type="button"
                :attributes="
                    \Filament\Support\prepare_inherited_attributes(
                        new \Filament\Support\View\ComponentAttributeBag([
                            'wire:click' => 'resetTypeFilter',
                            'wire:loading.attr' => 'disabled',
                            'wire:target' => 'resetTypeFilter',
                        ])
                    )
                "
            >
                {{ $resetLabel }}
            </x-filament::button>
        </div>
    </div>
</x-filament::dropdown>
