@props([
    'chartKey',
    'values',
    'labels' => null,
    'color' => 'gray',
    'sparklineClass' => '',
])

@php
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\StatsOverviewWidgetComponent\StatComponent\StatsOverviewWidgetStatChartComponent;

    $chartLabels = $labels ?? array_keys($values);
    $chartValues = array_values($values);
@endphp

<div x-data="{ statsOverviewStatChart() {} }">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('stats-overview/stat/chart', 'filament/widgets') }}"
        wire:ignore
        x-data="statsOverviewStatChart({
                    key: @js($chartKey),
                    labels: @js($chartLabels),
                    values: @js($chartValues),
                })"
        {{
            (new FilamentComponentAttributeBag)
                ->color(StatsOverviewWidgetStatChartComponent::class, $color)
                ->class(array_filter([
                    'fi-wi-stats-overview-stat-chart',
                    $sparklineClass,
                ]))
        }}
    >
        <canvas x-ref="canvas" aria-hidden="true"></canvas>

        <span
            x-ref="backgroundColorElement"
            class="fi-wi-stats-overview-stat-chart-bg-color"
        ></span>

        <span
            x-ref="borderColorElement"
            class="fi-wi-stats-overview-stat-chart-border-color"
        ></span>
    </div>
</div>
