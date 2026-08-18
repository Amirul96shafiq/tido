@props([
    'titleIndicator' => 'calm',
    'heading' => '',
])

<span class="inline-flex items-center gap-2">
    <span class="relative flex size-2 shrink-0" aria-hidden="true">
        <span
            @class([
                'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                'bg-red-500' => $titleIndicator === 'alert',
                'bg-gray-400 dark:bg-gray-500' => $titleIndicator === 'calm',
            ])
        ></span>
        <span
            @class([
                'relative inline-flex size-2 rounded-full',
                'bg-red-500' => $titleIndicator === 'alert',
                'bg-gray-400 dark:bg-gray-500' => $titleIndicator === 'calm',
            ])
        ></span>
    </span>
    {{ $heading }}
</span>
