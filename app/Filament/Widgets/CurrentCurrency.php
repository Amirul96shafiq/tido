<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;

class CurrentCurrency extends Widget
{
    use HasDashboardSectionId;

    public const SECTION_CURRENCY_RATE = 'currency-rate';

    public const SERIES_DAYS = 30;

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
     *     sourceDisplay: string|null,
     *     chartRates: list<float>,
     *     hasChart: bool,
     *     hasSeriesStats: bool,
     *     changeDelta: float|null,
     *     changePercent: float|null,
     *     changeDirection: 'up'|'down'|'flat'|null,
     *     changeDisplay: string|null,
     *     lowDisplay: string|null,
     *     highDisplay: string|null,
     *     avgDisplay: string|null,
     * }
     */
    protected function getViewData(): array
    {
        $emptyStats = [
            'hasSeriesStats' => false,
            'changeDelta' => null,
            'changePercent' => null,
            'changeDirection' => null,
            'changeDisplay' => null,
            'lowDisplay' => null,
            'highDisplay' => null,
            'avgDisplay' => null,
        ];

        $rateDetails = $this->resolveRateDetails();

        if (($rateDetails['unavailable'] ?? false) || ! isset($rateDetails['rate'])) {
            return [
                'unavailable' => true,
                'rate' => null,
                'rateDisplay' => null,
                'effectiveDate' => null,
                'provider' => null,
                'sourceDisplay' => null,
                'chartRates' => [],
                'hasChart' => false,
                ...$emptyStats,
            ];
        }

        $series = $this->resolveSeries();
        $chartRates = array_map(
            static fn (array $point): float => (float) $point['rate'],
            $series,
        );
        $seriesStats = $this->summarizeSeries($chartRates);

        return [
            'unavailable' => false,
            'rate' => (float) $rateDetails['rate'],
            'rateDisplay' => '1 USD = RM '.number_format((float) $rateDetails['rate'], 4, '.', ','),
            'effectiveDate' => Carbon::parse((string) $rateDetails['effective_date'])->format('d M Y'),
            'provider' => (string) $rateDetails['provider'],
            'sourceDisplay' => Carbon::parse((string) $rateDetails['effective_date'])->format('d M Y')
                .' • '
                .(string) $rateDetails['provider'],
            'chartRates' => $chartRates,
            'hasChart' => $chartRates !== [],
            ...$seriesStats,
        ];
    }

    /**
     * @param  list<float>  $chartRates
     * @return array{
     *     hasSeriesStats: bool,
     *     changeDelta: float|null,
     *     changePercent: float|null,
     *     changeDirection: 'up'|'down'|'flat'|null,
     *     changeDisplay: string|null,
     *     lowDisplay: string|null,
     *     highDisplay: string|null,
     *     avgDisplay: string|null,
     * }
     */
    private function summarizeSeries(array $chartRates): array
    {
        if ($chartRates === []) {
            return [
                'hasSeriesStats' => false,
                'changeDelta' => null,
                'changePercent' => null,
                'changeDirection' => null,
                'changeDisplay' => null,
                'lowDisplay' => null,
                'highDisplay' => null,
                'avgDisplay' => null,
            ];
        }

        $low = min($chartRates);
        $high = max($chartRates);
        $avg = array_sum($chartRates) / count($chartRates);
        $first = $chartRates[array_key_first($chartRates)];
        $last = $chartRates[array_key_last($chartRates)];
        $delta = $last - $first;
        $percent = $first > 0.0 ? ($delta / $first) * 100 : 0.0;

        $direction = match (true) {
            $delta > 0.00005 => 'up',
            $delta < -0.00005 => 'down',
            default => 'flat',
        };

        $deltaSign = $delta > 0.00005 ? '+' : ($delta < -0.00005 ? '−' : '');
        $percentSign = $percent > 0.00005 ? '+' : ($percent < -0.00005 ? '−' : '');

        return [
            'hasSeriesStats' => true,
            'changeDelta' => $delta,
            'changePercent' => $percent,
            'changeDirection' => $direction,
            'changeDisplay' => sprintf(
                '%s%s (%s%s%%) %dD',
                $deltaSign,
                number_format(abs($delta), 4, '.', ''),
                $percentSign,
                number_format(abs($percent), 2, '.', ''),
                self::SERIES_DAYS,
            ),
            'lowDisplay' => number_format($low, 4, '.', ''),
            'highDisplay' => number_format($high, 4, '.', ''),
            'avgDisplay' => number_format($avg, 4, '.', ''),
        ];
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
