@php
    $hasData = ($hasData ?? false) === true;
    $budget = $budget ?? [];
@endphp

@if (! $hasData)
    <p class="text-sm text-gray-500 dark:text-gray-400">No performance data yet.</p>
@else
    <x-budget-performance-row
        :budget="$budget"
        title-wire-key="budget-form-performance-title"
    />
@endif
