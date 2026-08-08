@php
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\ChartWidgetComponent;

    $hasMaxHeight = filled($chartHeight) && $chartHeight !== '100%';
    $defaultMyr = $unavailable ? '' : number_format((float) $rate, 4, '.', '');
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
            ])
            ->class(['h-full', 'fi-wi-current-currency'])
    "
>
    <x-filament::section class="h-full">
        <x-slot name="heading">USD to MYR</x-slot>

        @if ($unavailable)
            <div class="flex flex-1 flex-col justify-center gap-2 py-4">
                <p class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    Unavailable
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Current exchange rate unavailable
                </p>
            </div>
        @else
            <div class="flex flex-1 flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <p class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ $rateDisplay }}
                    </p>
                    <p class="text-sm text-primary-600 dark:text-primary-400">
                        1 USD as of {{ $effectiveDate }} via {{ $provider }}
                    </p>
                </div>

                <div
                    class="fi-wi-current-currency-converter flex flex-col gap-2"
                    x-data="{
                        rate: {{ json_encode((float) $rate) }},
                        usd: '1',
                        myr: @js($defaultMyr),
                        usdOnTop: true,
                        syncFromUsd() {
                            const value = Number.parseFloat(this.usd);

                            this.myr = Number.isFinite(value)
                                ? (value * this.rate).toFixed(4)
                                : '';
                        },
                        syncFromMyr() {
                            const value = Number.parseFloat(this.myr);

                            this.usd = Number.isFinite(value) && this.rate > 0
                                ? (value / this.rate).toFixed(4)
                                : '';
                        },
                        swap() {
                            this.usdOnTop = ! this.usdOnTop;
                        },
                    }"
                >
                    <template x-if="usdOnTop">
                        <div class="flex flex-col gap-2">
                            <label class="sr-only" for="currency-converter-usd">USD amount</label>
                            <div class="flex items-stretch overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                                <input
                                    id="currency-converter-usd"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="any"
                                    x-model="usd"
                                    x-on:input="syncFromUsd()"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                />
                                <span class="flex items-center border-l border-gray-950/10 px-3 text-sm font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    USD
                                </span>
                            </div>
                            <label class="sr-only" for="currency-converter-myr">MYR amount</label>
                            <div class="flex items-stretch overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                                <input
                                    id="currency-converter-myr"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="any"
                                    x-model="myr"
                                    x-on:input="syncFromMyr()"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                />
                                <span class="flex items-center border-l border-gray-950/10 px-3 text-sm font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    MYR
                                </span>
                            </div>
                        </div>
                    </template>

                    <template x-if="! usdOnTop">
                        <div class="flex flex-col gap-2">
                            <label class="sr-only" for="currency-converter-myr">MYR amount</label>
                            <div class="flex items-stretch overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                                <input
                                    id="currency-converter-myr"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="any"
                                    x-model="myr"
                                    x-on:input="syncFromMyr()"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                />
                                <span class="flex items-center border-l border-gray-950/10 px-3 text-sm font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    MYR
                                </span>
                            </div>
                            <label class="sr-only" for="currency-converter-usd">USD amount</label>
                            <div class="flex items-stretch overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                                <input
                                    id="currency-converter-usd"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="any"
                                    x-model="usd"
                                    x-on:input="syncFromUsd()"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                />
                                <span class="flex items-center border-l border-gray-950/10 px-3 text-sm font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    USD
                                </span>
                            </div>
                        </div>
                    </template>

                    <button
                        type="button"
                        x-on:click="swap()"
                        class="inline-flex items-center gap-1.5 self-start text-xs font-medium text-primary-600 transition hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        <x-filament::icon
                            icon="heroicon-m-arrows-up-down"
                            class="h-3.5 w-3.5"
                        />
                        Swap currencies
                    </button>
                </div>

                <div class="fi-wi-current-currency-chart mt-auto flex flex-col gap-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        30-day trend
                    </p>

                    @if ($hasChart)
                        <div
                            x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                            wire:ignore
                            data-chart-type="line"
                            x-data="chart({
                                        cachedData: @js($this->getChartData()),
                                        options: @js($this->getChartOptions()),
                                        type: 'line',
                                    })"
                            {{
                                (new FilamentComponentAttributeBag)
                                    ->color(ChartWidgetComponent::class, 'info')
                                    ->class([
                                        'fi-wi-chart-frame',
                                        'fi-wi-chart-canvas-ctn',
                                        'fi-wi-chart-frame-no-aspect-ratio',
                                        'fi-wi-currency-rate-chart',
                                    ])
                            }}
                        >
                            <canvas
                                x-ref="canvas"
                                role="img"
                                aria-label="USD to MYR exchange rate over the last 30 days"
                                @style([
                                    'width: 100%',
                                    ('max-height: '.e($chartHeight)) => $hasMaxHeight,
                                ])
                            ></canvas>

                            <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                            <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                            <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                            <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Rate history unavailable
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
