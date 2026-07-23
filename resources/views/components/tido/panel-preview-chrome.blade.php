@props([
    'collapsible' => false,
])

@php
    $logoFullLight = asset('images/tido_dark_logo.png');
    $logoFullDark = asset('images/tido_light_logo.png');
    $logoCompactLight = asset('images/tido_dark_logo_c.png');
    $logoCompactDark = asset('images/tido_light_logo_c.png');
    $collapsible = (bool) $collapsible;
@endphp

{{--
    Mini panel chrome for Personalize previews.
    Desktop arrangement: brand in sidebar; topbar has empty profile circle.
--}}
<div {{ $attributes->class(['flex h-full w-full']) }}>
    <div
        @class([
            'flex h-full shrink-0 flex-col gap-2 border-e border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-slate-800',
            'tido-sidebar-preview-rail transition-[width] duration-200' => $collapsible,
            'tido-stylized-preview-rail w-28' => ! $collapsible,
        ])
        @if ($collapsible)
            x-bind:class="collapsed ? 'w-12' : 'w-28'"
        @endif
    >
        <div
            @class([
                'mb-1 flex h-8 items-center',
                'px-1' => ! $collapsible,
            ])
            @if ($collapsible)
                x-bind:class="collapsed ? 'justify-center px-0' : 'px-1'"
            @endif
        >
            <span
                class="flex w-full items-center"
                @if ($collapsible)
                    x-show="! collapsed"
                    x-cloak
                @endif
            >
                <img
                    src="{{ $logoFullLight }}"
                    alt=""
                    class="h-7 w-auto max-w-full object-contain dark:hidden"
                />
                <img
                    src="{{ $logoFullDark }}"
                    alt=""
                    class="hidden h-7 w-auto max-w-full object-contain dark:block"
                />
            </span>

            @if ($collapsible)
                <span
                    class="flex items-center justify-center"
                    x-show="collapsed"
                    x-cloak
                >
                    <img
                        src="{{ $logoCompactLight }}"
                        alt=""
                        class="size-7 object-contain dark:hidden"
                    />
                    <img
                        src="{{ $logoCompactDark }}"
                        alt=""
                        class="hidden size-7 object-contain dark:block"
                    />
                </span>
            @endif
        </div>

        @foreach (range(1, 3) as $item)
            <div
                @class([
                    'flex h-6 items-center rounded',
                    $item === 1 ? 'bg-amber-500/20' : 'bg-transparent',
                    ! $collapsible ? 'gap-1.5 px-1.5' : null,
                ])
                @if ($collapsible)
                    x-bind:class="collapsed ? 'justify-center px-0' : 'gap-1.5 px-1.5'"
                @endif
            >
                <span
                    @class([
                        'h-2.5 w-2.5 shrink-0 rounded-sm',
                        $item === 1 ? 'bg-amber-500' : 'bg-gray-400 dark:bg-gray-500',
                    ])
                ></span>
                <span
                    @class([
                        'h-1.5 flex-1 rounded-full',
                        $item === 1 ? 'bg-amber-500/60' : 'bg-gray-300 dark:bg-gray-600',
                    ])
                    @if ($collapsible)
                        x-show="! collapsed"
                        x-cloak
                    @endif
                ></span>
            </div>
        @endforeach
    </div>

    <div class="relative flex min-w-0 flex-1 flex-col overflow-hidden">
        {{ $background ?? '' }}

        <div class="relative z-10 flex h-9 shrink-0 items-center justify-end gap-2 border-b border-gray-200 bg-white/90 px-2 dark:border-gray-700 dark:bg-slate-800/90">
            <span
                class="size-6 shrink-0 rounded-full bg-gray-200 ring-1 ring-gray-300 dark:bg-slate-700 dark:ring-slate-600"
                aria-hidden="true"
            ></span>
        </div>

        <div class="relative z-10 flex min-h-0 flex-1 flex-col gap-2 p-3">
            {{ $slot }}
        </div>
    </div>
</div>
