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

    /**
     * @var array{rate?: float, effective_date?: string, provider?: string, unavailable?: bool}
     */
    public array $rateDetails = [];

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-overview-with-section-id';

    public static function dashboardSectionId(): string
    {
        return self::SECTION_CURRENCY_RATE;
    }

    public function mount(ExchangeRateService $exchangeRates): void
    {
        try {
            $rateDetails = $exchangeRates->latest('USD', 'MYR');

            $this->rateDetails = [
                'rate' => (float) $rateDetails['rate'],
                'effective_date' => (string) $rateDetails['effective_date'],
                'provider' => (string) $rateDetails['provider'],
            ];
        } catch (CurrencyConversionException $exception) {
            Log::warning('Codex debug currency widget unavailable', [
                'reason' => $exception->getMessage(),
            ]);

            $this->rateDetails = [
                'unavailable' => true,
            ];
        }
    }

    protected function getStats(): array
    {
        if (($this->rateDetails['unavailable'] ?? false) || ! isset($this->rateDetails['rate'])) {
            return [
                Stat::make('USD to MYR', 'Unavailable')
                    ->description('Current exchange rate unavailable')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('gray'),
            ];
        }

        $effectiveDate = Carbon::parse((string) $this->rateDetails['effective_date'])
            ->format('d M Y');

        return [
            Stat::make(
                'USD to MYR',
                'RM '.number_format((float) $this->rateDetails['rate'], 4, '.', ','),
            )
                ->description('1 USD as of '.$effectiveDate.' via '.$this->rateDetails['provider'])
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('info'),
        ];
    }
}
