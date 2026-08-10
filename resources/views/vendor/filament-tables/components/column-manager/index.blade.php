@props([
    'applyAction',
    'columns' => null,
    'hasReorderableColumns',
    'hasToggleableColumns',
    'headingTag' => 'h3',
    'reorderAnimationDuration' => 300,
    'resetActionPosition' => null,
])

@php
    $resetLabel = __('filament-tables::table.column_manager.actions.reset.label');
@endphp

<div
    x-data="filamentTableColumnManager({
                columns: $wire.entangle('tableColumns'),
                isLive: {{ $applyAction->isVisible() ? 'false' : 'true' }},
            })"
    class="fi-ta-col-manager"
>
    <div class="fi-ta-col-manager-body">
        <x-filament-tables::column-manager.content
            :columns="$columns"
            :has-reorderable-columns="$hasReorderableColumns"
            :has-toggleable-columns="$hasToggleableColumns"
            :reorder-animation-duration="$reorderAnimationDuration"
        />
    </div>

    <div class="fi-ta-col-manager-actions-ctn">
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
                        'wire:click' => 'resetTableColumnManager',
                        'wire:loading.attr' => 'disabled',
                        'wire:target' => 'resetTableColumnManager',
                        'x-on:click' => 'resetDeferredColumns',
                    ])
                )
            "
        >
            {{ $resetLabel }}
        </x-filament::button>
    </div>
</div>
