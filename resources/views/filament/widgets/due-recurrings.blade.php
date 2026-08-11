@php
    /** @var bool $canManageRecurrings */
    /** @var string $contentHeight */
    /** @var list<array<string, mixed>> $items */
    /** @var string $manageUrl */
    /** @var string $totalAmount */
    /** @var int $totalCount */
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
            ], escape: false)
            ->class(['fi-wi-due-recurrings'])
    "
>
    <x-filament::section>
        <x-slot name="heading">
            {{ $totalCount }} Recurring {{ $totalCount === 1 ? 'Due' : 'Dues' }}
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $totalAmount }}
                </span>
                @if ($canManageRecurrings)
                    <x-filament::button
                        tag="a"
                        size="sm"
                        color="primary"
                        icon="heroicon-m-arrow-right"
                        :href="$manageUrl"
                    >
                        Manage
                    </x-filament::button>
                @endif
            </div>
        </x-slot>

        @if (count($items) === 0)
            <div
                class="fi-wi-due-recurrings-empty flex flex-1 items-center justify-center"
                style="min-height: {{ $contentHeight }}"
            >
                <x-empty-state-panel
                    heading="No recurring due"
                    description="Active reminders appear here when an occurrence is due or overdue."
                    icon="heroicon-o-arrow-path"
                />
            </div>
        @else
            <div
                @if ($canManageRecurrings)
                    wire:sort="reorderRecurrings"
                @endif
                class="custom-scrollbar mt-3 flex flex-1 flex-col gap-1 overflow-y-auto pr-2"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                @foreach ($items as $item)
                    <div
                        wire:key="due-recurrings-{{ $item['id'] }}"
                        @if ($item['can_reorder'] ?? false)
                            wire:sort:item="{{ $item['recurring_id'] }}"
                        @endif
                        class="-mx-1 flex items-center gap-2 rounded-xl px-3 py-2.5 transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-slate-700/60"
                    >
                        @if ($item['can_reorder'] ?? false)
                            <button
                                type="button"
                                wire:sort:handle
                                class="flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-600 dark:hover:text-gray-200"
                            >
                                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
                            </button>
                        @endif

                        @php
                            $itemTag = filled($item['edit_url'] ?? null) ? 'a' : 'div';
                            $hasGoal = $item['goalTarget'] !== null && $item['goalTarget'] > 0;
                            $progress = (float) ($item['progress'] ?? 0);
                        @endphp

                        <{{ $itemTag }}
                            @if (filled($item['edit_url'] ?? null))
                                wire:navigate
                                wire:sort:ignore
                                href="{{ $item['edit_url'] }}"
                            @endif
                            class="focus-visible:ring-primary-500 flex min-w-0 flex-1 items-center gap-2 rounded-lg text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <div
                                x-data="{ overflowing: false }"
                                x-init="
                                    const measure = () => {
                                        const marqueeText = $refs.marqueeText;

                                        if (!marqueeText) {
                                            overflowing = false;
                                            return;
                                        }

                                        $el.style.setProperty(
                                            '--tido-marquee-clip',
                                            $el.clientWidth + 'px',
                                        );
                                        overflowing = marqueeText.scrollWidth > $el.clientWidth;
                                    };
                                    $nextTick(measure);
                                    new ResizeObserver(() => measure()).observe($el);
                                "
                                class="tido-text-marquee-clip relative min-w-0 flex-1 overflow-hidden"
                            >
                                <span
                                    x-ref="marqueeText"
                                    class="inline-block font-semibold whitespace-nowrap text-gray-800 dark:text-gray-200"
                                    :class="{ 'tido-text-marquee': overflowing }"
                                >{{ $item['title'] }}</span>
                            </div>

                            <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">
                                {{ $item['cadence'] }}
                            </span>

                            @if ($item['is_shared'] ?? false)
                                <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/25 dark:text-primary-300">
                                    Shared
                                </span>
                            @endif

                            @if ($item['status'] === 'overdue')
                                <span class="shrink-0 text-xs font-semibold whitespace-nowrap text-red-500">
                                    Overdue · due {{ $item['dueOn'] }}
                                </span>
                            @else
                                <span class="shrink-0 text-xs whitespace-nowrap text-gray-400 dark:text-gray-500">
                                    Due {{ $item['dueOn'] }}
                                </span>
                            @endif

                            @if ($item['type'] !== '')
                                <span class="hidden shrink-0 text-xs whitespace-nowrap text-gray-400 sm:inline dark:text-gray-500">
                                    {{ $item['type'] }}
                                </span>
                            @endif

                            @if ($hasGoal)
                                <span class="hidden shrink-0 text-xs whitespace-nowrap text-gray-400 md:inline dark:text-gray-500">
                                    {{ number_format($progress, 0) }}% goal
                                </span>
                            @endif

                            <span class="shrink-0 font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {{ $item['amount'] }}
                            </span>
                        </{{ $itemTag }}>

                        <div wire:sort:ignore class="shrink-0">
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="skipOccurrence({{ $item['id'] }})"
                                wire:confirm="Skip this occurrence?"
                            >
                                Skip
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
