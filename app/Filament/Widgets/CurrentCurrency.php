<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;

class CurrentCurrency extends StatsOverviewWidget
{
    use HasDashboardSectionId;

    public const SECTION_CURRENCY_RATE = 'currency-rate';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    /**
     * @var array<string, int|string>
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected int|array|null $columns = 1;

    protected ?string $pollingInterval = null;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-overview-with-section-id';

    public static function dashboardSectionId(): string
    {
        return self::SECTION_CURRENCY_RATE;
    }

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    protected function getStats(): array
    {
        $rateDetails = $this->resolveRateDetails();

        if (($rateDetails['unavailable'] ?? false) || ! isset($rateDetails['rate'])) {
            return [
                Stat::make('USD to MYR', 'Unavailable')
                    ->description('Current exchange rate unavailable')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('gray'),
            ];
        }

        $effectiveDate = Carbon::parse((string) $rateDetails['effective_date'])
            ->format('d M Y');

        return [
            Stat::make(
                'USD to MYR',
                'RM '.number_format((float) $rateDetails['rate'], 4, '.', ','),
            )
                ->description('1 USD as of '.$effectiveDate.' via '.$rateDetails['provider'])
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('info'),
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
}
