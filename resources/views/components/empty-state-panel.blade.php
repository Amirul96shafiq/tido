@props([
    'heading',
    'description',
    'icon' => 'heroicon-o-magnifying-glass',
    'iconColor' => 'primary',
])

@php
    $iconColorClass = match ($iconColor) {
        'danger' => 'fi-no-empty-panel-icon-ctn-danger',
        'warning' => 'fi-no-empty-panel-icon-ctn-warning',
        'success' => 'fi-no-empty-panel-icon-ctn-success',
        'gray' => 'fi-no-empty-panel-icon-ctn-gray',
        default => 'fi-no-empty-panel-icon-ctn-primary',
    };
@endphp

<div {{ $attributes->class(['fi-no-empty-panel']) }}>
    <div @class(['fi-no-empty-panel-icon-ctn', $iconColorClass])>
        <x-filament::icon
            :icon="$icon"
            class="fi-no-empty-panel-icon"
        />
    </div>

    <h3 class="fi-no-empty-panel-heading">
        {{ $heading }}
    </h3>

    <p class="fi-no-empty-panel-description">
        {{ $description }}
    </p>

    @if (isset($actions) && ! $actions->isEmpty())
        <div class="fi-no-empty-panel-actions">
            {{ $actions }}
        </div>
    @endif
</div>
