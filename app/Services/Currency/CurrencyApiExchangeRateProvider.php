<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CurrencyApiExchangeRateProvider implements ExchangeRateProvider
{
    public const NAME = 'currencyapi';

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
    ): array {
        $baseCurrency = strtoupper(trim($baseCurrency));
        $targetCurrency = strtoupper(trim($targetCurrency));

        if (! $this->isCurrencyCode($baseCurrency) || ! $this->isCurrencyCode($targetCurrency)) {
            throw new CurrencyConversionException('The receipt currency is not a valid ISO 4217 code.');
        }

        $apiKey = config('services.currencyapi.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new CurrencyConversionException('The exchange-rate provider is not configured.');
        }

        try {
            $response = $this->request()
                ->get('/v3/historical', [
                    'date' => $date->toDateString(),
                    'base_currency' => $baseCurrency,
                    'currencies' => $targetCurrency,
                ])
                ->throw();
        } catch (CurrencyConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CurrencyConversionException(
                'The exchange-rate provider could not be reached.',
                previous: $exception,
            );
        }

        $rate = $response->json("data.{$targetCurrency}.value");
        if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate <= 0) {
            throw new CurrencyConversionException('The exchange-rate provider returned an invalid rate.');
        }

        return [
            'rate' => round((float) $rate, 10),
            'effective_date' => $date->toDateString(),
            'fetched_at' => now()->toDateTimeString(),
            'provider' => self::NAME,
        ];
    }

    private function request(): PendingRequest
    {
        $retryDelays = config('services.currencyapi.retry_delays', [100, 500, 1000]);
        $retryDelays = is_array($retryDelays)
            ? array_values(array_map(static fn (mixed $delay): int => max(0, (int) $delay), $retryDelays))
            : [100, 500, 1000];

        if ($retryDelays === []) {
            $retryDelays = [100, 500, 1000];
        }

        return Http::baseUrl(rtrim((string) config('services.currencyapi.base_url'), '/'))
            ->withHeaders([
                'apikey' => (string) config('services.currencyapi.api_key'),
                'Accept' => 'application/json',
            ])
            ->timeout(max(1, (int) config('services.currencyapi.timeout', 10)))
            ->connectTimeout(max(1, (int) config('services.currencyapi.connect_timeout', 3)))
            ->retry(
                $retryDelays,
                when: static function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError());
                },
            );
    }

    private function isCurrencyCode(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', $currency) === 1;
    }
}
