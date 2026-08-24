@php
    /** @var int $completedCount */
    /** @var string $contentHeight */
    /** @var int $dueCount */
    /** @var string $heading */
    /** @var bool $isEmpty */
    /** @var bool $isNextDueOverdue */
    /** @var ?string $nextDueDetail */
    /** @var ?string $nextDueLabel */
    /** @var int $overdueCount */
    /** @var string $paidAmount */
    /** @var string $remainingAmount */
    /** @var float $ringPercent */
    /** @var int $ringTotal */
    /** @var string $totalAmount */
    /** @var int $upcomingCount */

    $mutedChip = 'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-100';
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
            ], escape: false)
            ->class(['h-full', 'fi-wi-recurring-month-snapshot'])
    "
>
    <x-filament::section class="h-full">
        <x-slot name="heading">{{ $heading }}</x-slot>

        @if ($isEmpty)
            <div
                class="fi-wi-recurring-month-snapshot-empty flex flex-1 items-center justify-center"
                style="min-height: {{ $contentHeight }}"
            >
                <x-empty-state-panel
                    heading="No recurring payments this month"
                    description="Active reminders appear here when an occurrence is due, overdue, upcoming, or completed this month."
                    icon="heroicon-o-arrow-path"
                />
            </div>
        @else
            <div
                class="flex flex-1 flex-col items-center justify-center gap-3"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                <div class="flex flex-col items-center gap-1">
                    <div
                        class="relative size-28 shrink-0"
                        role="img"
                        aria-label="{{ $completedCount }} of {{ $ringTotal }} bills paid"
                    >
                        <svg viewBox="0 0 36 36" class="size-28 -rotate-90" aria-hidden="true">
                            <circle
                                cx="18"
                                cy="18"
                                r="15.9155"
                                fill="none"
                                stroke-width="3"
                                class="stroke-slate-200 dark:stroke-slate-700"
                            />
                            <circle
                                cx="18"
                                cy="18"
                                r="15.9155"
                                fill="none"
                                stroke-width="3"
                                stroke-linecap="round"
                                class="stroke-primary-500 dark:stroke-primary-400"
                                stroke-dasharray="{{ $ringPercent }} {{ 100 - $ringPercent }}"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-lg font-semibold tabular-nums text-gray-800 dark:text-gray-200">
                                <x-count-up :value="$completedCount" />
                            </span>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                of {{ $ringTotal }}
                            </span>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Paid</span>
                </div>

                <div class="flex flex-col items-center gap-0.5 text-center">
                    <span class="text-xl font-bold tabular-nums text-gray-800 dark:text-gray-200">
                        {!! \Xplodman\CountUp\Facades\CountUpStat::animate($paidAmount) !!}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        Paid of {!! \Xplodman\CountUp\Facades\CountUpStat::animate($totalAmount) !!}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        {!! \Xplodman\CountUp\Facades\CountUpStat::animate($remainingAmount) !!} remaining
                    </span>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <span
                        @class([
                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            'bg-primary-100 text-primary-700 dark:bg-primary-400/25 dark:text-primary-300' => $completedCount > 0,
                            'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-100' => $completedCount === 0,
                        ])
                    >
                        Paid {{ $completedCount }}
                    </span>
                    <span
                        @class([
                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            'bg-red-100 text-red-700 dark:bg-red-400/25 dark:text-red-300' => $overdueCount > 0,
                            'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-100' => $overdueCount === 0,
                        ])
                    >
                        Overdue {{ $overdueCount }}
                    </span>
                    <span
                        @class([
                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            'bg-amber-100 text-amber-800 dark:bg-amber-400/25 dark:text-amber-300' => $dueCount > 0,
                            'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-100' => $dueCount === 0,
                        ])
                    >
                        Due {{ $dueCount }}
                    </span>
                    <span @class([$mutedChip])>
                        Upcoming {{ $upcomingCount }}
                    </span>
                </div>

                @if (filled($nextDueLabel) && filled($nextDueDetail))
                    <div
                        @class([
                            'flex max-w-full flex-col items-center px-1 text-center text-xs',
                            'font-semibold text-red-500' => $isNextDueOverdue,
                            'text-gray-400 dark:text-gray-500' => ! $isNextDueOverdue,
                        ])
                    >
                        <span>{{ $nextDueLabel }}</span>
                        <span class="max-w-full truncate">{{ $nextDueDetail }}</span>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
