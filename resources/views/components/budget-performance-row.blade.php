@props([
    'budget' => [],
    'titleWireKey' => null,
])

@php
    use App\Helpers\MoneyDisplay;

    $name = (string) ($budget['name'] ?? 'Budget');
    $icon = (string) ($budget['icon'] ?? 'heroicon-o-banknotes');
    $color = (string) ($budget['color'] ?? '#FFD07D');
    $amount = (float) ($budget['amount'] ?? 0);
    $spent = (float) ($budget['spent'] ?? 0);
    $percentage = (float) ($budget['percentage'] ?? 0);
    $rawPercentage = (float) ($budget['raw_percentage'] ?? $percentage);
    $period = (string) ($budget['period'] ?? 'Monthly');
    $isShared = ($budget['is_shared'] ?? false) === true;
    $statusColor = (string) ($budget['status_color'] ?? 'emerald');

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
@endphp

<div class="flex min-w-0 flex-1 flex-col gap-2">
    <div class="flex min-w-0 items-start justify-between gap-2 text-sm sm:items-center">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <span
                class="flex size-6 shrink-0 items-center justify-center rounded-md"
                style="background-color: color-mix(in srgb, {{ $color }} 18%, transparent); color: {{ $color }};"
            >
                <x-filament::icon :icon="$icon" class="size-3.5" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2">
                <x-tido.text-marquee
                    class="min-w-0 flex-1"
                    text-class="inline-block font-semibold whitespace-nowrap text-gray-800 dark:text-gray-200"
                    wire:key="{{ $titleWireKey ?? 'budget-performance-title' }}"
                >{{ $name }}</x-tido.text-marquee>
                <div class="flex w-fit shrink-0 flex-row flex-wrap items-center gap-1.5">
                    <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">{{ $period }}</span>
                    @if ($isShared)
                        <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/25 dark:text-primary-300">Shared</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-0.5 text-right whitespace-nowrap sm:flex-row sm:items-baseline sm:gap-1">
            <span class="font-bold text-gray-700 dark:text-gray-300">{{ MoneyDisplay::withPrefix($spent) }}</span>
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">/ {{ MoneyDisplay::withPrefix($amount) }}</span>
        </div>
    </div>

    <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
        <div
            class="h-full rounded-full transition-all duration-1000 ease-out {{ $barColorClass }}"
            style="width: {{ $percentage }}%; box-shadow: 0 0 10px {{ $glowColor }};"
        ></div>
    </div>

    <div class="mt-2 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
        <span>
            @if ($rawPercentage >= 100)
                <span class="flex items-center gap-1 font-semibold text-red-500">
                    <span class="h-1.5 w-1.5 animate-ping rounded-full bg-red-500"></span>
                    Exceeded by {{ number_format($rawPercentage - 100, 1) }}%
                </span>
            @elseif ($statusColor === 'amber')
                <span class="font-semibold text-amber-500">
                    Approaching limit ({{ number_format($rawPercentage, 1) }}%)
                </span>
            @else
                <span class="text-gray-400 dark:text-gray-500">
                    {{ number_format($rawPercentage, 1) }}% consumed
                </span>
            @endif
        </span>
        <span>
            {{ MoneyDisplay::withPrefix(max(0, $amount - $spent)) }} remaining
        </span>
    </div>
</div>
