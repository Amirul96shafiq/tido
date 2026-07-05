<x-filament-widgets::widget class="h-full">
    <x-filament::section class="h-full">
        <x-slot name="heading">
            Budget Performance
        </x-slot>

        @if(empty($budgets))
            <div class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm font-medium">
                No active budgets configured.
            </div>
        @else
            <div class="flex flex-col gap-6 mt-3 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                @foreach($budgets as $budget)
                    <div class="flex flex-col gap-2 group p-3 rounded-xl transition-all duration-300">
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $budget['color'] }}; box-shadow: 0 0 8px {{ $budget['color'] }}80;"></span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $budget['name'] }}</span>
                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-md">{{ ucfirst($budget['period']) }}</span>
                            </div>
                            <div class="font-bold text-gray-700 dark:text-gray-300">
                                RM {{ number_format($budget['spent'], 2) }} <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">/ RM {{ number_format($budget['amount'], 2) }}</span>
                            </div>
                        </div>
                        
                        <div class="w-full bg-gray-200 dark:bg-gray-800 h-2.5 rounded-full overflow-hidden relative">
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
                                RM {{ number_format(max(0, $budget['amount'] - $budget['spent']), 2) }} remaining
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
