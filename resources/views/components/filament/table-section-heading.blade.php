@props([
    'heading',
    'class' => '',
])

<h3
    @class([
        'tido-table-section-heading',
        'text-base font-semibold leading-6 text-gray-950 dark:text-white',
        'mb-3',
        $class,
    ])
>
    {{ $heading }}
</h3>
