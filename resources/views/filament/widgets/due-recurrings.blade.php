@php
    /** @var bool $canManageRecurrings */
    /** @var bool $canReorderRecurrings */
    /** @var string $contentHeight */
    /** @var list<array<string, mixed>> $items */
    /** @var string $manageUrl */
    /** @var string $titleIndicator */
    /** @var int $totalCount */
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
            ], escape: false)
            ->class(['fi-wi-due-recurrings'])
    "
>
    <x-due-recurrings-panel
        :can-manage-recurrings="$canManageRecurrings"
        :can-reorder-recurrings="$canReorderRecurrings"
        :content-height="$contentHeight"
        :items="$items"
        :manage-url="$manageUrl"
        :title-indicator="$titleIndicator"
        :total-count="$totalCount"
        :interactive="true"
        :as-section="true"
        item-key-prefix="due-recurrings"
    />

    <x-filament-actions::modals />
</x-filament-widgets::widget>
