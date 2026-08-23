@php
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
            <div
                class="fi-wi-budget-status-empty flex flex-1 items-center justify-center"
                style="min-height: {{ $contentHeight }}"
            >
                <x-empty-state-panel
                    heading="No budgets yet"
                    description="Create a budget to track spending against a limit."
                    icon="heroicon-o-banknotes"
                    icon-color="gray"
                    class="fi-wi-chart-empty-panel"
                >
                    <x-slot name="actions">
                        @if ($canCreateBudgets ?? true)
                            <x-filament::button
                                :href="\App\Filament\Resources\Budgets\BudgetResource::getUrl('create')"
                                tag="a"
                                color="primary"
                                icon="heroicon-m-plus"
                            >
                                New budget
                            </x-filament::button>
                        @else
                            <span
                                class="inline-flex"
                                x-tooltip="{
                                    content: @js($createDeniedMessage ?? 'Only Primary can access this feature.'),
                                    theme: $store.theme,
                                }"
                            >
                                <x-filament::button
                                    disabled
                                    color="primary"
                                    icon="heroicon-m-plus"
                                >
                                    New budget
                                </x-filament::button>
                            </span>
                        @endif
                    </x-slot>
                </x-empty-state-panel>
            </div>
        @else
            <div
                @if ($canReorderBudgets ?? false)
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
                            class="focus-visible:ring-primary-500 flex min-w-0 flex-1 rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <x-budget-performance-row
                                :budget="$budget"
                                :title-wire-key="'budget-status-title-'.$budget['id']"
                            />
                        </{{ $budgetTag }}>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
