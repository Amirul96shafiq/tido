@php
    $logoFullLight = asset('images/tido_dark_logo.png');
    $logoFullDark = asset('images/tido_light_logo.png');
    $logoCompactLight = asset('images/tido_dark_logo_c.png');
    $logoCompactDark = asset('images/tido_light_logo_c.png');
    $lightBackground = asset('images/bg-l-v7.png');
    $darkBackground = asset('images/bg-d-v7.png');
@endphp

{{--
    Mini panel chrome for Personalize previews.
    Requires parent x-data with `collapsed` and `enabled`.
    Desktop arrangement: brand in sidebar; topbar has empty profile circle.
--}}
<div {{ $attributes->class(['flex h-full w-full']) }}>
    <div
        class="tido-sidebar-preview-rail flex h-full shrink-0 flex-col gap-2 border-e border-gray-200 bg-gray-50 p-2 transition-[width] duration-200 dark:border-gray-700 dark:bg-slate-800"
        x-bind:class="collapsed ? 'w-12' : 'w-28'"
    >
        <div
            class="mb-1 flex h-8 items-center"
            x-bind:class="collapsed ? 'justify-center px-0' : 'px-1'"
        >
            <span
                class="flex w-full items-center"
                x-show="! collapsed"
                x-cloak
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
        </div>

        @foreach (range(1, 3) as $item)
            <div
                @class([
                    'flex h-6 items-center rounded',
                    $item === 1 ? 'bg-amber-500/20' : 'bg-transparent',
                ])
                x-bind:class="collapsed ? 'justify-center px-0' : 'gap-1.5 px-1.5'"
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
                    x-show="! collapsed"
                    x-cloak
                ></span>
            </div>
        @endforeach
    </div>

    <div class="relative flex min-w-0 flex-1 flex-col overflow-hidden bg-white dark:bg-slate-900">
        {{-- Art under the focus-mode veil. Do not toggle art via Tailwind opacity-* —
            .tido-panel-bg-art sets opacity in CSS and wins over utility classes.
            Solid chrome underlay keeps preview intensity identical across Personalize mocks. --}}
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
