@php
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\StatsOverviewWidgetComponent\StatComponent\StatsOverviewWidgetStatChartComponent;

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
    <x-filament::section class="h-full fi-wi-current-currency-section">
        @if ($unavailable)
            <div class="flex flex-1 flex-col justify-center gap-2 py-2">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    USD to MYR
                </p>
                <p class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    Unavailable
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Current exchange rate unavailable
                </p>
            </div>
        @else
            <div @class([
                'flex flex-1 flex-col gap-4',
                'pb-8' => $hasChart,
            ])>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-start sm:gap-6">
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            USD to MYR
                        </p>
                        <p class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ $rateDisplay }}
                        </p>
                        <p class="text-sm text-primary-600 dark:text-primary-400">
                            1 USD as of {{ $effectiveDate }} via {{ $provider }}
                        </p>
                    </div>

                    <div
                        class="fi-wi-current-currency-converter flex min-w-0 flex-col gap-1.5"
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
                            <div class="flex flex-col gap-1.5">
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
                                        class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                    />
                                    <span class="flex items-center border-l border-gray-950/10 px-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
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
                                        class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                    />
                                    <span class="flex items-center border-l border-gray-950/10 px-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                        MYR
                                    </span>
                                </div>
                            </div>
                        </template>

                        <template x-if="! usdOnTop">
                            <div class="flex flex-col gap-1.5">
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
                                        class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                    />
                                    <span class="flex items-center border-l border-gray-950/10 px-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
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
                                        class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-950 outline-none focus:ring-0 dark:text-white"
                                    />
                                    <span class="flex items-center border-l border-gray-950/10 px-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
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
                </div>

                @if ($hasChart)
                    <div x-data="{ statsOverviewStatChart() {} }">
                        <div
                            x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('stats-overview/stat/chart', 'filament/widgets') }}"
                            wire:ignore
                            x-data="statsOverviewStatChart({
                                        key: 'currency-rate-sparkline',
                                        labels: @js(array_keys($chartRates)),
                                        values: @js(array_values($chartRates)),
                                    })"
                            {{
                                (new FilamentComponentAttributeBag)
                                    ->color(StatsOverviewWidgetStatChartComponent::class, 'primary')
                                    ->class(['fi-wi-stats-overview-stat-chart', 'fi-wi-currency-rate-sparkline'])
                            }}
                        >
                            <canvas
                                x-ref="canvas"
                                aria-hidden="true"
                                aria-label="USD to MYR exchange rate over the last 30 days"
                            ></canvas>

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
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Rate history unavailable
                    </p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
