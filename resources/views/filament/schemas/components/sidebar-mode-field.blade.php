<div
    class="tido-sidebar-preview relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
    style="aspect-ratio: 32 / 9;"
    x-data="{
        get collapsed() {
            return ! this.$store.sidebar.isOpen;
        },
    }"
>
    <div class="flex w-full bg-white dark:bg-slate-900" style="height: 200%;">
        {{-- Mini sidebar --}}
        <div
            class="tido-sidebar-preview-rail flex h-full shrink-0 flex-col gap-2 border-e border-gray-200 bg-gray-50 p-2 transition-[width] duration-200 dark:border-gray-700 dark:bg-slate-800"
            x-bind:class="collapsed ? 'w-10' : 'w-28'"
        >
            <div
                class="mb-1 flex h-6 items-center rounded bg-amber-500/90"
                x-bind:class="collapsed ? 'justify-center px-0' : 'px-2'"
            >
                <span class="h-2.5 w-2.5 shrink-0 rounded-sm bg-zinc-900"></span>
                <span
                    class="ms-1.5 h-1.5 flex-1 rounded-full bg-zinc-900/40"
                    x-show="! collapsed"
                    x-cloak
                ></span>
            </div>

            @foreach (range(1, 3) as $item)
                <div
                    @class([
                        'flex h-6 items-center rounded',
                        $item === 1
                            ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                            : 'bg-transparent',
                    ])
                    x-bind:class="collapsed ? 'justify-center px-0' : 'gap-1.5 px-1.5'"
                >
                    <span
                        @class([
                            'h-2.5 w-2.5 shrink-0 rounded-sm',
                            $item === 1
                                ? 'bg-amber-500'
                                : 'bg-gray-400 dark:bg-gray-500',
                        ])
                    ></span>
                    <span
                        @class([
                            'h-1.5 flex-1 rounded-full',
                            $item === 1
                                ? 'bg-amber-500/60'
                                : 'bg-gray-300 dark:bg-gray-600',
                        ])
                        x-show="! collapsed"
                        x-cloak
                    ></span>
                </div>
            @endforeach
        </div>

        {{-- Mini content --}}
        <div class="flex min-w-0 flex-1 flex-col gap-2 p-3">
            <div class="h-3 w-1/3 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <div class="h-16 w-full rounded-md bg-gray-100 dark:bg-slate-800"></div>
            <div class="flex gap-2">
                <div class="h-10 flex-1 rounded-md bg-gray-100 dark:bg-slate-800"></div>
                <div class="h-10 flex-1 rounded-md bg-gray-100 dark:bg-slate-800"></div>
            </div>
        </div>
    </div>

    <div class="absolute inset-block-start-2 inset-inline-start-2 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
        <span x-text="collapsed ? 'Collapsed' : 'Expanded'">Expanded</span>
    </div>
</div>
