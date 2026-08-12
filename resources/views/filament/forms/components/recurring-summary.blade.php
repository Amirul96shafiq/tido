@php
    $title = $title ?? 'Untitled recurring';
    $amountLine = $amountLine ?? 'Amount unset';
    $scheduleLine = $scheduleLine ?? 'Schedule incomplete';
    $nextDueLine = $nextDueLine ?? 'Next due: —';
    $labelLine = $labelLine ?? 'No label';
    $ownershipLine = $ownershipLine ?? 'Primary';
    $statusLine = $statusLine ?? 'Active';
@endphp

<div class="flex flex-col gap-3 text-sm">
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold text-gray-950 dark:text-white">
            {{ $title }}
        </p>
        <p class="text-gray-600 dark:text-gray-300">
            {{ $amountLine }}
        </p>
    </div>

    <div class="flex flex-col gap-1 text-gray-600 dark:text-gray-300">
        <p>{{ $scheduleLine }}</p>
        <p>{{ $nextDueLine }}</p>
        <p>{{ $labelLine }}</p>
        <p>{{ $ownershipLine }}</p>
    </div>

    <p @class([
        'font-medium',
        'text-emerald-700 dark:text-emerald-400' => $statusLine === 'Active',
        'text-gray-500 dark:text-gray-400' => $statusLine !== 'Active',
    ])>
        {{ $statusLine }}
    </p>
</div>
