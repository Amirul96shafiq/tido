@php
    use App\Helpers\MoneyDisplay;

    $hasData = ($hasData ?? false) === true;
    $periodLabel = $periodLabel ?? null;
    $status = $status ?? 'On track';
    $statusColor = $statusColor ?? 'emerald';
    $spent = (float) ($spent ?? 0);
    $amount = (float) ($amount ?? 0);
    $remaining = (float) ($remaining ?? 0);
    $percentage = (float) ($percentage ?? 0);
    $rawPercentage = (float) ($rawPercentage ?? $percentage);
    $barWidth = min(100, max(0, $percentage));

    $barColorClass = match ($statusColor) {
        'red' => 'bg-gradient-to-r from-red-500 to-rose-600',
        'amber' => 'bg-gradient-to-r from-amber-400 to-orange-500',
        default => 'bg-gradient-to-r from-[#FFD07D] to-[#FFA524]',
    };

    $glowColor = match ($statusColor) {
        'red' => 'rgba(239, 68, 68, 0.4)',
        'amber' => 'rgba(245, 158, 11, 0.4)',
        default => 'rgba(255, 208, 125, 0.4)',
    };

    $statusBadgeClass = match ($statusColor) {
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        default => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    };
@endphp

@if (! $hasData)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        No performance data yet.
    </p>
@else
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $periodLabel }}
            </p>
            <span @class([
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold',
                $statusBadgeClass,
            ])>
                {{ $status }}
            </span>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">Spent</span>
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ MoneyDisplay::withPrefix($spent) }}
                </span>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">Limit</span>
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ MoneyDisplay::withPrefix($amount) }}
                </span>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">Remaining</span>
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ MoneyDisplay::withPrefix($remaining) }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
                <div
                    class="h-full rounded-full transition-all duration-1000 ease-out {{ $barColorClass }}"
                    style="width: {{ $barWidth }}%; box-shadow: 0 0 10px {{ $glowColor }};"
                ></div>
            </div>

            <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>
                    @if ($rawPercentage >= 100)
                        <span class="font-semibold text-red-500">
                            Exceeded by {{ number_format($rawPercentage - 100, 1) }}%
                        </span>
                    @elseif ($statusColor === 'amber')
                        <span class="font-semibold text-amber-500">
                            Approaching limit ({{ number_format($rawPercentage, 1) }}%)
                        </span>
                    @else
                        {{ number_format($rawPercentage, 1) }}% consumed
                    @endif
                </span>
                <span>{{ MoneyDisplay::withPrefix($remaining) }} remaining</span>
            </div>
        </div>
    </div>
@endif
