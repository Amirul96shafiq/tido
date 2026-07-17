@php
    use App\Helpers\MoneyDisplay;
@endphp

<x-filament-widgets::widget class="h-full fi-wi-budget-status">
    <x-filament::section class="h-full">
        <x-slot name="heading">
            Budget Performance ({{ $monthLabel ?? now()->format('F Y') }})
        </x-slot>

        @if(empty($budgets))
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
                        <x-filament::button
                            :href="\App\Filament\Resources\Budgets\BudgetResource::getUrl('create')"
                            tag="a"
                            color="primary"
                            icon="heroicon-m-plus"
                        >
                            New budget
                        </x-filament::button>
                    </x-slot>
                </x-empty-state-panel>
            </div>
        @else
            <div
                class="flex flex-1 flex-col gap-6 mt-3 overflow-y-auto custom-scrollbar pr-2"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                @foreach($budgets as $budget)
                    <div class="flex flex-col gap-2 group p-3 rounded-xl transition-all duration-300">
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $budget['color'] }}; box-shadow: 0 0 8px {{ $budget['color'] }}80;"></span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $budget['name'] }}</span>
                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-md">{{ ucfirst($budget['period']) }}</span>
                            </div>
                            <div class="font-bold text-gray-700 dark:text-gray-300">
                                {{ MoneyDisplay::withPrefix($budget['spent']) }} <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">/ {{ MoneyDisplay::withPrefix($budget['amount']) }}</span>
                            </div>
                        </div>
                        
                        <div class="w-full bg-gray-200 dark:bg-white/15 h-2.5 rounded-full overflow-hidden relative">
                            @php
                                $barColorClass = match($budget['status_color']) {
                                    'red' => 'bg-gradient-to-r from-red-500 to-rose-600',
                                    'amber' => 'bg-gradient-to-r from-amber-400 to-orange-500',
                                    default => 'bg-gradient-to-r from-[#FFD07D] to-[#FFA524]',
                                };
                                $glowColor = match($budget['status_color']) {
                                    'red' => 'rgba(239, 68, 68, 0.4)',
                                    'amber' => 'rgba(245, 158, 11, 0.4)',
                                    default => 'rgba(255, 208, 125, 0.4)',
                                };
                            @endphp
                            <div class="h-full rounded-full transition-all duration-1000 ease-out {{ $barColorClass }}"
                                 style="width: {{ $budget['percentage'] }}%; box-shadow: 0 0 10px {{ $glowColor }};">
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-xs text-gray-400 dark:text-gray-500">
                            <span>
                                @if($budget['raw_percentage'] >= 100)
                                    <span class="text-red-500 font-semibold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                                        Exceeded by {{ number_format($budget['raw_percentage'] - 100, 1) }}%
                                    </span>
                                @elseif($budget['raw_percentage'] >= 85)
                                    <span class="text-amber-500 font-semibold">
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
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
