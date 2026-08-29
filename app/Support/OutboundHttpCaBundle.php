<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;

final class OutboundHttpCaBundle
{
    public static function path(): ?string
    {
        $configured = config('services.outbound_http.cainfo');

        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $default = base_path('bootstrap/cacert.pem');

        if (is_readable($default)) {
            return $default;
        }

        return null;
    }

    public static function applyVerify(PendingRequest $request): PendingRequest
    {
        $path = self::path();

        if ($path === null) {
            return $request;
        }

        return $request->withOptions(['verify' => $path]);
    }

    public static function guzzleVerifyOption(): bool|string
    {
        $path = self::path();

        return $path ?? true;
    }

    public static function sslVerificationMessage(): string
    {
        return 'SSL certificate verification failed. Set OUTBOUND_HTTP_CAINFO to a CA bundle path, or use bootstrap/cacert.pem on Windows.';
    }
}
