@props([
    'canReorderRecurrings' => false,
    'contentHeight' => null,
    'hasHeight' => false,
    'items' => [],
    'interactive' => true,
    'itemKeyPrefix' => 'due-recurrings',
])

@if (count($items) === 0)
    <div
        class="fi-wi-due-recurrings-empty flex flex-1 items-center justify-center"
        @if ($hasHeight)
            style="min-height: {{ $contentHeight }}"
        @endif
    >
        <x-empty-state-panel
            heading="No recurring payments due"
            description="Active reminders appear here when an occurrence is due, overdue, or upcoming this month."
            icon="heroicon-o-arrow-path"
        />
    </div>
@else
    <div
        @if ($interactive && $canReorderRecurrings)
            wire:sort="reorderRecurrings"
        @endif
        class="custom-scrollbar grid grid-cols-1 gap-1 overflow-y-auto pr-2"
        @if ($hasHeight)
            style="max-height: {{ $contentHeight }}"
        @endif
    >
        @foreach ($items as $item)
            <x-due-recurring-row
                :item="$item"
                :interactive="$interactive"
                :can-reorder-recurrings="$canReorderRecurrings"
                :item-key-prefix="$itemKeyPrefix"
            />
        @endforeach
    </div>
@endif
