<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Widgets\CurrentCurrency;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Console\Command;
use Throwable;

class RefreshCurrencyRatesCommand extends Command
{
    protected $signature = 'currency:refresh-rates
                            {--base=USD : Base currency ISO code}
                            {--target=MYR : Target currency ISO code}
                            {--days= : Sparkline window in days (defaults to widget SERIES_DAYS)}';

    protected $description = 'Refresh cached dashboard exchange rates from CurrencyAPI (scheduled daily at midnight)';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        $base = strtoupper(trim((string) $this->option('base')));
        $target = strtoupper(trim((string) $this->option('target')));
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption)
            ? max(1, (int) $daysOption)
            : CurrentCurrency::SERIES_DAYS;

        try {
            $result = $exchangeRates->refreshDashboardRates($base, $target, $days);
        } catch (CurrencyConversionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Currency rate refresh failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Refreshed %s→%s latest=%.4f (%d series points).',
            $base,
            $target,
            $result['latest']['rate'],
            count($result['series']),
        ));

        return self::SUCCESS;
    }
}
