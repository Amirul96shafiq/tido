@props([
    'canManageRecurrings' => true,
    'canReorderRecurrings' => false,
    'contentHeight' => null,
    'items' => [],
    'manageUrl' => '',
    'titleIndicator' => 'calm',
    'totalCount' => 0,
    'interactive' => true,
    'asSection' => true,
    'itemKeyPrefix' => 'due-recurrings',
])

@php
    $heading = $totalCount.' Recurring Payment '.($totalCount === 1 ? 'Due' : 'Dues');
    $hasHeight = filled($contentHeight);
    $itemList = is_array($items) ? $items : [];
@endphp

@if ($asSection)
    <x-filament::section>
        <x-slot name="heading">
            <x-due-recurrings-heading :title-indicator="$titleIndicator" :heading="$heading" />
        </x-slot>

        @if ($canManageRecurrings)
            <x-slot name="afterHeader">
                <x-due-recurrings-manage-button
                    :interactive="$interactive"
                    :manage-url="$manageUrl"
                />
            </x-slot>
        @endif

        <x-due-recurrings-body
            :can-reorder-recurrings="$canReorderRecurrings"
            :content-height="$contentHeight"
            :has-height="$hasHeight"
            :items="$itemList"
            :interactive="$interactive"
            :item-key-prefix="$itemKeyPrefix"
        />
    </x-filament::section>
@else
    <div @class(['fi-due-recurrings-preview', 'fi-due-recurrings-preview-inert' => ! $interactive])>
        <x-due-recurrings-body
            :can-reorder-recurrings="$canReorderRecurrings"
            :content-height="$contentHeight"
            :has-height="$hasHeight"
            :items="$itemList"
            :interactive="$interactive"
            :item-key-prefix="$itemKeyPrefix"
        />
    </div>
@endif
