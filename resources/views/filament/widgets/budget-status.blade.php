@php
    use App\Helpers\MoneyDisplay;

    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class(['h-full', 'fi-wi-budget-status'])
    "
>
    <x-filament::section class="h-full">
        <x-slot name="heading">Budget Performance ({{ $monthLabel ?? now()->format('F Y') }})</x-slot>

        @if (empty($budgets))
            @php
                $emptyDescription = ($canManageBudgets ?? true)
                    ? 'Create a budget to track spending against a limit.'
                    : 'Ask the Primary user to assign a budget for you.';
            @endphp
            <div
                class="fi-wi-budget-status-empty flex flex-1 items-center justify-center"
                style="min-height: {{ $contentHeight }}"
            >
                <x-empty-state-panel
                    heading="No budgets yet"
                    :description="$emptyDescription"
                    icon="heroicon-o-banknotes"
                    icon-color="gray"
                    class="fi-wi-chart-empty-panel"
                >
                    @if ($canManageBudgets ?? true)
                        <x-slot name="actions">
                            <x-filament::button
                                :href="\App\Filament\Resources\Budgets\BudgetResource::getUrl('create')"
                                tag="a"
                                color="primary"
                                icon="heroicon-m-plus"
                            >
                                New budget
                            </x-filament::button>
                        </x-slot>
                    @endif
                </x-empty-state-panel>
            </div>
        @else
            <div
                @if ($canManageBudgets ?? true)
                    wire:sort="reorderBudgets"
                @endif
                class="custom-scrollbar mt-3 flex flex-1 flex-col gap-6 overflow-y-auto pr-2"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                @foreach ($budgets as $budget)
                    <div
                        wire:key="budget-status-{{ $budget['id'] }}"
                        @if ($budget['can_reorder'] ?? false)
                            wire:sort:item="{{ $budget['id'] }}"
                        @endif
                        class="-mx-1 flex items-center gap-2 rounded-xl p-4 transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-slate-700/60"
                    >
                        @if ($budget['can_reorder'] ?? false)
                            <button
                                type="button"
                                wire:sort:handle
                                class="flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-600 dark:hover:text-gray-200"
                            >
                                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
                            </button>
                        @endif

                        @php
                            $budgetTag = filled($budget['edit_url'] ?? null) ? 'a' : 'div';
                        @endphp
                        <{{ $budgetTag }}
                            @if (filled($budget['edit_url'] ?? null))
                                wire:navigate
                                wire:sort:ignore
                                href="{{ $budget['edit_url'] }}"
                            @endif
                            class="focus-visible:ring-primary-500 flex min-w-0 flex-1 flex-col gap-2 rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <div class="flex min-w-0 items-start justify-between gap-2 text-sm sm:items-center">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span
                                        class="flex size-6 shrink-0 items-center justify-center rounded-md"
                                        style="background-color: color-mix(in srgb, {{ $budget['color'] }} 18%, transparent); color: {{ $budget['color'] }};"
                                    >
                                        <x-filament::icon :icon="$budget['icon']" class="size-3.5" />
                                    </span>
                                    <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2">
                                        <x-tido.text-marquee
                                            class="min-w-0 flex-1"
                                            text-class="inline-block font-semibold whitespace-nowrap text-gray-800 dark:text-gray-200"
                                            wire:key="budget-status-title-{{ $budget['id'] }}"
                                        >{{ $budget['name'] }}</x-tido.text-marquee>
                                        <div class="flex w-fit shrink-0 flex-row flex-wrap items-center gap-1.5">
                                            <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">{{ ucfirst($budget['period']) }}</span>
                                            @if ($budget['is_shared'] ?? false)
                                                <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/25 dark:text-primary-300">Shared</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-0.5 text-right whitespace-nowrap sm:flex-row sm:items-baseline sm:gap-1">
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ MoneyDisplay::withPrefix($budget['spent']) }}</span>
                                    <span class="text-xs font-normal text-gray-400 dark:text-gray-500">/ {{ MoneyDisplay::withPrefix($budget['amount']) }}</span>
                                </div>
                            </div>

                            <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
                                @php
                                    $barColorClass = match ($budget['status_color']) {
                                        'red' => 'bg-gradient-to-r from-red-500 to-rose-600',
                                        'amber' => 'bg-gradient-to-r from-amber-400 to-orange-500',
                                        default => 'bg-gradient-to-r from-[#FFD07D] to-[#FFA524]',
                                    };
                                    $glowColor = match ($budget['status_color']) {
                                        'red' => 'rgba(239, 68, 68, 0.4)',
                                        'amber' => 'rgba(245, 158, 11, 0.4)',
                                        default => 'rgba(255, 208, 125, 0.4)',
                                    };
                                @endphp
                                <div
                                    class="h-full rounded-full transition-all duration-1000 ease-out {{ $barColorClass }}"
                                    style="width: {{ $budget['percentage'] }}%; box-shadow: 0 0 10px {{ $glowColor }};"
                                ></div>
                            </div>

                            <div class="mt-2 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                                <span>
                                    @if ($budget['raw_percentage'] >= 100)
                                        <span class="flex items-center gap-1 font-semibold text-red-500">
                                            <span class="h-1.5 w-1.5 animate-ping rounded-full bg-red-500"></span>
                                            Exceeded by {{ number_format($budget['raw_percentage'] - 100, 1) }}%
                                        </span>
                                    @elseif ($budget['status_color'] === 'amber')
                                        <span class="font-semibold text-amber-500">
                                            Approaching limit ({{ number_format($budget['raw_percentage'], 1) }}%)
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">
                                            {{ number_format($budget['raw_percentage'], 1) }}% consumed
                                        </span>
                                    @endif
                                </span>
                                <span>
                                    {{ MoneyDisplay::withPrefix(max(0, $budget['amount'] - $budget['spent'])) }} remaining
                                </span>
                            </div>
                        </{{ $budgetTag }}>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
