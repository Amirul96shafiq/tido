@props([
    'item' => [],
    'interactive' => true,
    'canReorderRecurrings' => false,
    'itemKeyPrefix' => 'due-recurrings',
])

@php
    $canReorder = ($item['can_reorder'] ?? false) === true;
    $showDragHandle = $canReorder || $interactive === false;
    $hasGoal = ($item['goalTarget'] ?? null) !== null && (float) $item['goalTarget'] > 0;
    $progress = (float) ($item['progress'] ?? 0);
    $isCompleted = (bool) ($item['is_completed'] ?? false);
    $isSkipped = (bool) ($item['is_skipped'] ?? false);
    $editUrl = $interactive ? ($item['edit_url'] ?? null) : null;
    $itemTag = filled($editUrl) ? 'a' : 'div';
@endphp

<div
    wire:key="{{ $itemKeyPrefix }}-{{ $item['id'] }}"
    @if ($interactive && $canReorder)
        wire:sort:item="{{ $item['recurring_id'] }}"
    @endif
    @class([
        '-mx-1 flex min-w-0 items-center gap-2 rounded-xl px-2 py-1.5 sm:gap-3 sm:px-3',
        'transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-slate-700/60' => $interactive,
        'opacity-50' => $isCompleted,
    ])
>
    @if ($showDragHandle)
        @if ($interactive && $canReorder)
            <button
                type="button"
                wire:sort:handle
                class="flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-600 dark:hover:text-gray-200"
            >
                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
            </button>
        @else
            <div
                class="pointer-events-none flex size-6 shrink-0 items-center justify-center rounded-md text-gray-400 opacity-50"
                aria-hidden="true"
            >
                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
            </div>
        @endif
    @elseif ($canReorderRecurrings)
        <div class="size-6 shrink-0" aria-hidden="true"></div>
    @endif

    <div
        @if ($interactive)
            wire:sort:ignore
        @endif
        @class([
            'shrink-0',
            'opacity-50' => $isSkipped,
        ])
        aria-label="{{ $item['owner_name'] }}"
        x-tooltip="{
            content: @js($item['owner_name']),
            theme: $store.theme,
        }"
    >
        <x-filament::avatar
            :src="$item['owner_avatar_url']"
            :alt="$item['owner_name']"
            size="sm"
        />
    </div>

    <{{ $itemTag }}
        @if (filled($editUrl))
            wire:navigate
            wire:sort:ignore
            href="{{ $editUrl }}"
        @endif
        @class([
            'min-w-0 flex-1 rounded-lg',
            'focus-visible:ring-primary-500 focus-visible:ring-2 focus-visible:outline-none' => filled($editUrl),
            'opacity-50' => $isSkipped,
        ])
    >
        <div class="flex min-w-0 w-full flex-col gap-0.5">
            <x-tido.text-marquee
                class="min-w-0 w-full"
                text-class="inline-flex items-center gap-2 leading-5 whitespace-nowrap"
                wire:key="{{ $itemKeyPrefix }}-title-{{ $item['id'] }}"
            >
                <span class="text-sm leading-5 font-semibold text-gray-800 dark:text-gray-200">
                    {{ $item['title'] }}
                </span>

                <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">
                    {{ $item['cadence'] }}
                </span>
            </x-tido.text-marquee>

            <x-tido.text-marquee
                class="min-w-0 w-full"
                text-class="inline-flex items-center gap-x-1.5 whitespace-nowrap text-xs leading-4 text-gray-400 dark:text-gray-500"
                wire:key="{{ $itemKeyPrefix }}-meta-{{ $item['id'] }}-{{ $item['status'] }}-{{ (int) $isCompleted }}-{{ (int) $isSkipped }}"
            >
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

                @if (($item['type'] ?? '') !== '')
                    <span aria-hidden="true">·</span>
                    <span>{{ $item['type'] }}</span>
                @endif

                @if ($hasGoal)
                    <span aria-hidden="true">·</span>
                    <span>{{ number_format($progress, 0) }}% of goal</span>
                @endif
            </x-tido.text-marquee>
        </div>
    </{{ $itemTag }}>

    <div
        @if ($interactive)
            wire:sort:ignore
        @endif
        class="flex shrink-0 items-center gap-2 sm:gap-3"
    >
        @if ($item['is_shared'] ?? false)
            <span
                @class([
                    'inline-flex w-fit shrink-0 items-center rounded-full bg-primary-100 px-2 py-0.5 text-[11px] leading-none font-medium text-primary-700 dark:bg-primary-400/25 dark:text-primary-300',
                    'opacity-50' => $isSkipped,
                ])
            >
                Shared
            </span>
        @endif

        <span
            @class([
                'shrink-0 text-sm leading-none font-bold whitespace-nowrap text-gray-700 tabular-nums dark:text-gray-300',
                'opacity-50' => $isSkipped,
            ])
        >
            {!! \Xplodman\CountUp\Facades\CountUpStat::animate($item['amount']) !!}
        </span>

        <div
            @class([
                'flex items-center',
                'pointer-events-none opacity-50' => ! $interactive,
            ])
        >
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
                    @if ($interactive)
                        <x-filament::button
                            size="sm"
                            color="primary"
                            wire:click="mountAction('confirmRevertOccurrence', { occurrenceId: {{ $item['id'] }} })"
                        >
                            Revert Back
                        </x-filament::button>
                    @else
                        <x-filament::button
                            size="sm"
                            color="primary"
                            disabled
                        >
                            Revert Back
                        </x-filament::button>
                    @endif
                </div>
            @elseif ($isCompleted)
                <x-filament::button
                    size="sm"
                    color="gray"
                    disabled
                >
                    Skip
                </x-filament::button>
            @elseif ($interactive)
                <x-filament::button
                    size="sm"
                    color="gray"
                    wire:click="mountAction('confirmSkipOccurrence', { occurrenceId: {{ $item['id'] }} })"
                >
                    Skip
                </x-filament::button>
            @else
                <x-filament::button
                    size="sm"
                    color="gray"
                    disabled
                >
                    Skip
                </x-filament::button>
            @endif
        </div>
    </div>
</div>
