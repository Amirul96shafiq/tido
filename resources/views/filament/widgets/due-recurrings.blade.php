@php
    /** @var bool $canManageRecurrings */
    /** @var string $contentHeight */
    /** @var list<array<string, mixed>> $items */
    /** @var string $manageUrl */
    /** @var string $openAmount */
    /** @var string $titleIndicator */
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
                {{ $totalCount }} Recurring Payment {{ $totalCount === 1 ? 'Due' : 'Dues' }}
            </span>
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex items-center gap-3">
                <div class="flex shrink-0 flex-col items-end gap-0.5 text-right text-sm whitespace-nowrap sm:flex-row sm:items-baseline sm:gap-1">
                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ $openAmount }}</span>
                    <span class="text-xs font-normal text-gray-400 dark:text-gray-500">/ {{ $totalAmount }}</span>
                </div>
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
                    heading="No recurring payments due"
                    description="Active reminders appear here when an occurrence is due, overdue, or upcoming this month."
                    icon="heroicon-o-arrow-path"
                />
            </div>
        @else
            <div
                @if ($canManageRecurrings)
                    wire:sort="reorderRecurrings"
                @endif
                class="custom-scrollbar flex flex-1 flex-col gap-1 overflow-y-auto pr-2"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                @foreach ($items as $item)
                    <div
                        wire:key="due-recurrings-{{ $item['id'] }}"
                        @if ($item['can_reorder'] ?? false)
                            wire:sort:item="{{ $item['recurring_id'] }}"
                        @endif
                        @class([
                            '-mx-1 flex items-center gap-3 rounded-xl px-3 py-3 transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-slate-700/60',
                            'opacity-50' => ($item['is_completed'] ?? false),
                        ])
                    >
                        @if ($item['can_reorder'] ?? false)
                            <button
                                type="button"
                                wire:sort:handle
                                class="flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-600 dark:hover:text-gray-200"
                            >
                                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
                            </button>
                        @elseif ($canManageRecurrings)
                            <div class="size-6 shrink-0" aria-hidden="true"></div>
                        @endif

                        @php
                            $itemTag = filled($item['edit_url'] ?? null) ? 'a' : 'div';
                            $hasGoal = $item['goalTarget'] !== null && $item['goalTarget'] > 0;
                            $progress = (float) ($item['progress'] ?? 0);
                            $isCompleted = (bool) ($item['is_completed'] ?? false);
                            $isSkipped = (bool) ($item['is_skipped'] ?? false);
                        @endphp

                        <{{ $itemTag }}
                            @if (filled($item['edit_url'] ?? null))
                                wire:navigate
                                wire:sort:ignore
                                href="{{ $item['edit_url'] }}"
                            @endif
                            @class([
                                'focus-visible:ring-primary-500 flex min-w-0 flex-1 items-center justify-between gap-4 rounded-lg focus-visible:ring-2 focus-visible:outline-none',
                                'opacity-50' => $isSkipped,
                            ])
                        >
                            {{-- Left: identity + secondary meta (bills-timeline / payment-card pattern) --}}
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <div class="flex min-w-0 items-center gap-2">
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
                                        class="tido-text-marquee-clip relative min-w-0 max-w-full overflow-hidden sm:max-w-[14rem] md:max-w-[18rem]"
                                    >
                                        <span
                                            x-ref="marqueeText"
                                            class="inline-block text-sm font-semibold whitespace-nowrap text-gray-800 dark:text-gray-200"
                                            :class="{ 'tido-text-marquee': overflowing }"
                                        >{{ $item['title'] }}</span>
                                    </div>

                                    <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">
                                        {{ $item['cadence'] }}
                                    </span>

                                    @if ($item['is_shared'] ?? false)
                                        <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-medium text-primary-700 dark:bg-primary-400/25 dark:text-primary-300">
                                            Shared
                                        </span>
                                    @endif
                                </div>

                                <div class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-xs text-gray-400 dark:text-gray-500">
                                    @if ($isCompleted)
                                        <span>Completed · {{ $item['completedAt'] }}</span>
                                    @elseif ($isSkipped)
                                        <span>Skipped · {{ $item['dueOn'] }}</span>
                                    @elseif ($item['status'] === 'overdue')
                                        <span class="font-semibold text-red-500">
                                            Overdue · {{ $item['dueOn'] }}
                                        </span>
                                    @elseif ($item['status'] === 'upcoming')
                                        <span>Upcoming · {{ $item['dueOn'] }}</span>
                                    @else
                                        <span>Due {{ $item['dueOn'] }}</span>
                                    @endif

                                    @if ($item['type'] !== '')
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $item['type'] }}</span>
                                    @endif

                                    @if ($hasGoal)
                                        <span aria-hidden="true">·</span>
                                        <span>{{ number_format($progress, 0) }}% of goal</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Right: amount is the primary decision signal --}}
                            <span class="shrink-0 text-sm font-bold whitespace-nowrap text-gray-700 tabular-nums dark:text-gray-300">
                                {{ $item['amount'] }}
                            </span>
                        </{{ $itemTag }}>

                        <div wire:sort:ignore class="shrink-0">
                            @if ($isSkipped)
                                <div class="flex items-center gap-2">
                                    <div class="opacity-50">
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            disabled
                                        >
                                            Skipped
                                        </x-filament::button>
                                    </div>
                                    <x-filament::button
                                        size="sm"
                                        color="primary"
                                        wire:click="mountAction('confirmRevertOccurrence', { occurrenceId: {{ $item['id'] }} })"
                                    >
                                        Revert Back
                                    </x-filament::button>
                                </div>
                            @elseif ($isCompleted)
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    disabled
                                >
                                    Skip
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="mountAction('confirmSkipOccurrence', { occurrenceId: {{ $item['id'] }} })"
                                >
                                    Skip
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
