@php
    $hasData = ($hasData ?? false) === true;
    $budget = $budget ?? [];
    $emptyHeading = $emptyHeading ?? 'Set a budget limit to preview';
    $emptyDescription = $emptyDescription ?? 'Enter an amount under Limit & Period to see how current spending would track against this budget.';
@endphp

@if (! $hasData)
    <x-empty-state-panel
        :heading="$emptyHeading"
        :description="$emptyDescription"
        icon="heroicon-o-banknotes"
        icon-color="gray"
        class="fi-wi-chart-empty-panel"
    />
@else
    <x-budget-performance-row
        :budget="$budget"
        title-wire-key="budget-form-performance-title"
    />
@endif
