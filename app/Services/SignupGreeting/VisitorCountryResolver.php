<?php

declare(strict_types=1);

namespace App\Services\SignupGreeting;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class VisitorCountryResolver
{
    private const PRIVATE_IP_DEFAULT_COUNTRY = 'MY';

    public function resolve(?Request $request = null): ?string
    {
        $request ??= request();

        $headerCountry = $this->countryFromTrustedHeader($request);

        if ($headerCountry !== null) {
            return $headerCountry;
        }

        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return self::PRIVATE_IP_DEFAULT_COUNTRY;
        }

        if ($this->isPrivateOrReservedIp($ip)) {
            return self::PRIVATE_IP_DEFAULT_COUNTRY;
        }

        return $this->lookupPublicIpCountry($ip);
    }

    private function countryFromTrustedHeader(Request $request): ?string
    {
        $code = $request->header('CF-IPCountry');

        if (! is_string($code)) {
            return null;
        }

        $code = strtoupper(trim($code));

        if ($code === '' || $code === 'XX' || ! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return $code;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private function lookupPublicIpCountry(string $ip): ?string
    {
        $cacheKey = 'signup-greeting:country:'.hash('sha256', $ip);
        $cacheTtl = (int) config('services.signup_greeting.cache_ttl', 86400);

        /** @var ?string $country */
        $country = Cache::remember($cacheKey, $cacheTtl, function () use ($ip): ?string {
            return $this->fetchCountryFromProvider($ip);
        });

        return $country;
    }

    private function fetchCountryFromProvider(string $ip): ?string
    {
        $baseUrl = (string) config('services.signup_greeting.geoip_base_url', 'https://ipwho.is');
        $url = rtrim($baseUrl, '/').'/'.rawurlencode($ip);

        try {
            $response = Http::timeout((float) config('services.signup_greeting.timeout', 1.5))
                ->connectTimeout((float) config('services.signup_greeting.connect_timeout', 1))
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            if ($response->json('success') !== true) {
                return null;
            }

            $countryCode = $response->json('country_code');

            if (! is_string($countryCode)) {
                return null;
            }

            $countryCode = strtoupper(trim($countryCode));

            if ($countryCode === '' || ! preg_match('/^[A-Z]{2}$/', $countryCode)) {
                return null;
            }

            return $countryCode;
        } catch (ConnectionException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
