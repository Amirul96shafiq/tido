@php
    $longestPhrase = collect($phrases)->sortByDesc(static fn (string $phrase): int => strlen($phrase))->first() ?? $englishPhrase;
@endphp

<span
    class="tido-signup-greeting-heading"
    wire:key="{{ $wireKey }}"
    aria-label="{{ $englishPhrase }}"
    x-data="tidoSignupGreeting({ phrases: @js($phrases), holdMs: {{ (int) $holdMs }} })"
>
    <span class="tido-signup-greeting-heading__spacer" aria-hidden="true">{{ $longestPhrase }}</span>
    <span class="tido-signup-greeting-heading__text" aria-hidden="true">
        <span x-text="displayText"></span><span
            class="tido-signup-greeting-heading__caret"
            x-show="showCaret"
            x-transition:enter=""
            x-transition:leave=""
            aria-hidden="true"
        ></span>
    </span>
</span>
