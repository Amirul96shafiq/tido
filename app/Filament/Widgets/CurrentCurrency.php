<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;

class CurrentCurrency extends Widget
{
    use HasDashboardSectionId;

    public const SECTION_CURRENCY_RATE = 'currency-rate';

    public const SERIES_DAYS = 30;

    public const CHART_HEIGHT = '150px';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    /**
     * @var array<string, int|string>
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.current-currency';

    public static function dashboardSectionId(): string
    {
        return self::SECTION_CURRENCY_RATE;
    }

    /**
     * @return array{
     *     unavailable: bool,
     *     rate: float|null,
     *     rateDisplay: string|null,
     *     effectiveDate: string|null,
     *     provider: string|null,
     *     chartLabels: list<string>,
     *     chartRates: list<float>,
     *     hasChart: bool,
     *     chartHeight: string,
     * }
     */
    protected function getViewData(): array
    {
        $rateDetails = $this->resolveRateDetails();

        if (($rateDetails['unavailable'] ?? false) || ! isset($rateDetails['rate'])) {
            return [
                'unavailable' => true,
                'rate' => null,
                'rateDisplay' => null,
                'effectiveDate' => null,
                'provider' => null,
                'chartLabels' => [],
                'chartRates' => [],
                'hasChart' => false,
                'chartHeight' => self::CHART_HEIGHT,
            ];
        }

        $series = $this->resolveSeries();
        $chartLabels = array_map(
            static fn (array $point): string => Carbon::parse($point['date'])->format('d M'),
            $series,
        );
        $chartRates = array_map(
            static fn (array $point): float => (float) $point['rate'],
            $series,
        );

        return [
            'unavailable' => false,
            'rate' => (float) $rateDetails['rate'],
            'rateDisplay' => 'RM '.number_format((float) $rateDetails['rate'], 4, '.', ','),
            'effectiveDate' => Carbon::parse((string) $rateDetails['effective_date'])->format('d M Y'),
            'provider' => (string) $rateDetails['provider'],
            'chartLabels' => $chartLabels,
            'chartRates' => $chartRates,
            'hasChart' => $chartRates !== [],
            'chartHeight' => self::CHART_HEIGHT,
        ];
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    public function getChartData(): array
    {
        $viewData = $this->getViewData();

        return [
            'datasets' => [
                [
                    'label' => 'USD to MYR',
                    'data' => $viewData['chartRates'],
                    'borderColor' => '#38BDF8',
                    'backgroundColor' => 'rgba(56, 189, 248, 0.18)',
                    'borderWidth' => 2,
                    'borderCapStyle' => 'round',
                    'borderJoinStyle' => 'round',
                    'tension' => 0.35,
                    'cubicInterpolationMode' => 'monotone',
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'pointBackgroundColor' => '#38BDF8',
                    'pointBorderColor' => '#38BDF8',
                    'fill' => true,
                    'spanGaps' => true,
                ],
            ],
            'labels' => $viewData['chartLabels'],
        ];
    }

    public function getChartOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                const value = item.parsed?.y ?? item.raw ?? 0;
                                const numeric = Number(value);

                                if (!Number.isFinite(numeric)) {
                                    return 'Rate unavailable';
                                }

                                return `1 USD = RM ${numeric.toFixed(4)}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 6,
                            font: { size: 10 },
                        },
                        grid: {
                            display: false,
                        },
                    },
                    y: {
                        ticks: {
                            font: { size: 10 },
                            callback: (value) => Number(value).toFixed(2),
                        },
                    },
                },
            }
        JS);
    }

    /**
     * @return array{rate?: float, effective_date?: string, provider?: string, unavailable?: bool}
     */
    private function resolveRateDetails(): array
    {
        try {
            $rateDetails = app(ExchangeRateService::class)->latest('USD', 'MYR');

            return [
                'rate' => (float) $rateDetails['rate'],
                'effective_date' => (string) $rateDetails['effective_date'],
                'provider' => (string) $rateDetails['provider'],
            ];
        } catch (CurrencyConversionException $exception) {
            Log::warning('Currency widget rate unavailable', [
                'reason' => $exception->getMessage(),
            ]);

            return [
                'unavailable' => true,
            ];
        }
    }

    /**
     * @return list<array{date: string, rate: float}>
     */
    private function resolveSeries(): array
    {
        try {
            return app(ExchangeRateService::class)->series('USD', 'MYR', self::SERIES_DAYS);
        } catch (CurrencyConversionException $exception) {
            Log::warning('Currency widget series unavailable', [
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
