@php
    $hasData = ($hasData ?? false) === true;
    $emptyHeading = $emptyHeading ?? 'Set a title and due day to preview';
    $emptyDescription = $emptyDescription ?? 'Enter a title and due day under Details and Schedule to see how this recurring would appear on Recurring Payment Dues.';
    $items = $items ?? [];
    $canManageRecurrings = ($canManageRecurrings ?? true) === true;
    $canReorderRecurrings = ($canReorderRecurrings ?? true) === true;
    $manageUrl = $manageUrl ?? '';
    $titleIndicator = $titleIndicator ?? 'calm';
    $totalCount = (int) ($totalCount ?? 0);
@endphp

@if (! $hasData)
    <x-empty-state-panel
        :heading="$emptyHeading"
        :description="$emptyDescription"
        icon="heroicon-o-arrow-path"
        icon-color="gray"
        class="fi-wi-chart-empty-panel"
    />
@else
    <x-due-recurrings-panel
        :can-manage-recurrings="$canManageRecurrings"
        :can-reorder-recurrings="$canReorderRecurrings"
        :items="$items"
        :manage-url="$manageUrl"
        :title-indicator="$titleIndicator"
        :total-count="$totalCount"
        :interactive="false"
        :as-section="false"
        item-key-prefix="due-recurring-preview"
    />
@endif
