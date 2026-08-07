<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\CarbonInterface;

interface ExchangeRateProvider
{
    /**
     * @return array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }
     */
    public function rate(
        string $baseCurrency,
        string $targetCurrency,
        CarbonInterface $date,
    ): array;
}
