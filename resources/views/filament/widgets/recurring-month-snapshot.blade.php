@php
    /** @var int $completedCount */
    /** @var string $contentHeight */
    /** @var int $dueCount */
    /** @var ?string $nextDueOn */
    /** @var ?string $nextDueTitle */
    /** @var string $openAmount */
    /** @var int $overdueCount */
    /** @var float $ringPercent */
    /** @var int $ringTotal */
    /** @var string $totalAmount */
    /** @var int $upcomingCount */
    /** @var bool $isEmpty */
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
        <x-slot name="heading">This Month's Bills</x-slot>

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
                class="flex flex-1 flex-col items-center justify-center gap-4"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                <div
                    class="relative size-28 shrink-0"
                    role="img"
                    aria-label="{{ $completedCount }} of {{ $ringTotal }} bills completed"
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
                            {{ $completedCount }}
                        </span>
                        <span class="text-[11px] text-gray-400 dark:text-gray-500">
                            of {{ $ringTotal }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-0.5 text-center">
                    <span class="text-xl font-bold tabular-nums text-gray-800 dark:text-gray-200">
                        {{ $openAmount }}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        / {{ $totalAmount }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <span
                        @class([
                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            'bg-red-100 text-red-700 dark:bg-red-400/25 dark:text-red-300' => $overdueCount > 0,
                            'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-100' => $overdueCount === 0,
                        ])
                    >
                        Overdue {{ $overdueCount }}
                    </span>
                    <span class="inline-flex w-fit items-center rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">
                        Due {{ $dueCount }}
                    </span>
                    <span class="inline-flex w-fit items-center rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">
                        Upcoming {{ $upcomingCount }}
                    </span>
                </div>

                @if (filled($nextDueTitle) && filled($nextDueOn))
                    <p class="max-w-full truncate px-1 text-center text-xs text-gray-400 dark:text-gray-500">
                        {{ $nextDueTitle }} · {{ $nextDueOn }}
                    </p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
