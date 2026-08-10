@props([
    'applyAction',
    'form',
    'headingTag' => 'h3',
    'resetActionPosition' => null,
])

@php
    $resetLabel = __('filament-tables::table.filters.actions.reset.label');
@endphp

<div {{ $attributes->class(['fi-ta-filters']) }}>
    <div class="fi-ta-filters-body">
        {{ $form }}
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
                        'wire:click' => 'resetTableFiltersForm',
                        'wire:loading.attr' => 'disabled',
                        'wire:target' => 'resetTableFiltersForm',
                    ])
                )
            "
        >
            {{ $resetLabel }}
        </x-filament::button>
    </div>
</div>
