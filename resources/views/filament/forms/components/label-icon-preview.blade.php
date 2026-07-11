@php
    $icon = $icon ?? 'heroicon-o-tag';
    $color = $color ?? '#a1a1aa';
    $name = $name ?? 'Label preview';
@endphp

<div class="flex flex-col items-center gap-3 py-2">
    <div
        class="flex size-20 items-center justify-center rounded-2xl ring-1 ring-gray-950/5 dark:ring-white/10"
        style="background-color: color-mix(in srgb, {{ $color }} 18%, transparent); color: {{ $color }};"
    >
        <x-filament::icon
            :icon="$icon"
            class="size-10"
        />
    </div>

    <p class="text-sm font-medium text-gray-950 dark:text-white">
        {{ $name }}
    </p>
</div>
