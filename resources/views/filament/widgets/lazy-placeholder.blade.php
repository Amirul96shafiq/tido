@props([
    'columnSpan' => [],
    'columnStart' => [],
    'height' => null,
])

<div
    role="status"
    aria-busy="true"
    {{
        ($attributes ?? new \Filament\Support\View\ComponentAttributeBag)
            ->gridColumn($columnSpan, $columnStart)
            ->class([
                'fi-section',
                'fi-loading-section',
                'fi-wi-loading-section',
                'flex',
                'items-center',
                'justify-center',
            ])
            ->style([
                'height: ' . e($height ?? '8rem'),
            ])
    }}
>
    <span class="fi-sr-only">Loading widget</span>

    <x-filament::loading-indicator class="size-6 text-primary-500" />
</div>
