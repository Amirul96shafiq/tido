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
     *     chartRates: list<float>,
     *     hasChart: bool,
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
                'chartRates' => [],
                'hasChart' => false,
            ];
        }

        $series = $this->resolveSeries();
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
            'chartRates' => $chartRates,
            'hasChart' => $chartRates !== [],
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
