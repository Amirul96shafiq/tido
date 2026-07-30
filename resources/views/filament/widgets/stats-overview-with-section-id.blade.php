@php
    $columns = $this->getColumns();
    $pollingInterval = $this->getPollingInterval();

    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    $widgetAttributes = [
        'wire:poll.'.$pollingInterval => $pollingInterval ? true : null,
    ];

    if (method_exists($this, 'getDashboardSectionId')) {
        $widgetAttributes['id'] = $this->getDashboardSectionId();
    }
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge($widgetAttributes, escape: false)
            ->class([
                'fi-wi-stats-overview',
            ])
    "
>
    {{ $this->content }}
</x-filament-widgets::widget>
