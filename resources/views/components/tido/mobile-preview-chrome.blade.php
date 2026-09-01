@php
    $logoCompactLight = asset('images/tido_dark_logo_c.png');
    $logoCompactDark = asset('images/tido_light_logo_c.png');
    $lightBackground = asset('images/bg-l-v8.webp');
    $darkBackground = asset('images/bg-d-v8.webp');
@endphp

{{--
    Portrait mobile panel mock for Mobile Navigation Menu preview only.
    Requires parent x-data with `mobileNav` and `enabled` (stylized background).
--}}
<div {{ $attributes->class(['tido-mobile-preview-chrome relative flex h-full w-full flex-col overflow-hidden bg-white dark:bg-slate-900']) }}>
    <div
        class="tido-panel-bg-art tido-panel-bg-art--light absolute inset-0 dark:hidden"
        style="--tido-panel-bg-url: url('{{ $lightBackground }}');"
        aria-hidden="true"
    ></div>

    <div
        class="tido-panel-bg-art tido-panel-bg-art--dark absolute inset-0 hidden dark:block"
        style="--tido-panel-bg-url: url('{{ $darkBackground }}');"
        aria-hidden="true"
    ></div>

    <div
        class="absolute inset-0 z-[1] bg-white transition-opacity duration-200 dark:bg-slate-900"
        x-bind:class="enabled ? 'opacity-0' : 'opacity-100'"
        data-tido-preview-veil
        aria-hidden="true"
    ></div>

    <div
        class="tido-mobile-preview-topbar relative z-[2] flex h-8 shrink-0 items-center justify-between border-b border-gray-200 bg-white/90 px-2 dark:border-gray-700 dark:bg-slate-800/90"
        x-show="! mobileNav"
        x-cloak
    >
        <span class="flex items-center gap-1.5" aria-hidden="true">
            <img src="{{ $logoCompactLight }}" alt="" class="size-4 object-contain dark:hidden" />
            <img src="{{ $logoCompactDark }}" alt="" class="hidden size-4 object-contain dark:block" />
            <span class="h-1 w-8 rounded-full bg-gray-300 dark:bg-gray-600"></span>
        </span>

        <span
            class="size-5 shrink-0 rounded-full bg-gray-200 ring-1 ring-gray-300 dark:bg-slate-700 dark:ring-slate-600"
            aria-hidden="true"
        ></span>
    </div>

    <div class="relative z-[2] flex min-h-0 flex-1 flex-col gap-1.5 p-2">{{ $slot }}</div>

    <div
        class="tido-mobilenav-preview-bar relative z-[2] shrink-0 border-t border-gray-200 bg-white/90 dark:border-gray-700 dark:bg-slate-800/90"
        x-show="mobileNav"
        x-cloak
        aria-hidden="true"
    >
        <div class="tido-mobilenav-preview-bar-grid">
            <span class="tido-mobilenav-preview-slot">
                <span class="tido-mobilenav-preview-icon"></span>
            </span>
            <span class="tido-mobilenav-preview-slot">
                <span class="tido-mobilenav-preview-icon"></span>
            </span>
            <span class="tido-mobilenav-preview-slot tido-mobilenav-preview-slot--primary">
                <span class="tido-mobilenav-preview-icon tido-mobilenav-preview-icon--primary"></span>
            </span>
            <span class="tido-mobilenav-preview-slot">
                <span class="tido-mobilenav-preview-icon"></span>
            </span>
            <span class="tido-mobilenav-preview-slot">
                <span class="tido-mobilenav-preview-icon"></span>
            </span>
        </div>
    </div>
</div>
