@props([
    'label',
    'long' => null,
    'mono' => false,
])

@php
    $plainText = trim(strip_tags((string) $slot));
    $isLong = $long ?? (
        strlen($plainText) > 36
        || str_contains($plainText, '\\')
        || str_contains($plainText, 'http://')
        || str_contains($plainText, 'https://')
    );
@endphp

<div {{ $attributes->class(['fi-ollama-detail-row']) }}>
    <dt class="fi-ollama-detail-row__label">{{ $label }}</dt>
    <dd @class([
        'fi-ollama-detail-row__value',
        'fi-ollama-detail-row__value--long' => $isLong,
        'font-mono break-all' => $mono,
    ])>
        {{ $slot }}
    </dd>
</div>
