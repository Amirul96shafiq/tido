@props([
    'interactive' => true,
    'manageUrl' => '',
])

@if ($interactive)
    <x-filament::button
        tag="a"
        size="sm"
        color="primary"
        icon="heroicon-m-arrow-right"
        :href="$manageUrl"
    >
        Manage
    </x-filament::button>
@else
    <div class="pointer-events-none opacity-50">
        <x-filament::button
            size="sm"
            color="primary"
            icon="heroicon-m-arrow-right"
            disabled
        >
            Manage
        </x-filament::button>
    </div>
@endif
