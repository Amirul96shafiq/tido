<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\URL;

/**
 * Build absolute URLs for WhatsApp messages.
 *
 * Phones cannot open http://localhost — prefer WHATSAPP_PUBLIC_APP_URL, else LAN IP when APP_URL is loopback.
 */
final class WhatsAppPublicUrl
{
    public static function base(): string
    {
        $configured = trim((string) config('services.evolution.public_app_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || ! self::isLoopbackHost($host)) {
            return $appUrl;
        }

        $lanIp = self::detectLanIpv4();

        if ($lanIp === null) {
            return $appUrl;
        }

        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$lanIp.($port ? ':'.$port : '');
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withRoot(Closure $callback): mixed
    {
        $base = self::base();
        $previousRoot = URL::to('/');

        URL::forceRootUrl($base);

        try {
            return $callback();
        } finally {
            URL::forceRootUrl(rtrim($previousRoot, '/'));
        }
    }

    public static function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function detectLanIpv4(): ?string
    {
        $hostname = gethostname();

        if (! is_string($hostname) || $hostname === '') {
            return null;
        }

        $ips = @gethostbynamel($hostname);

        if (! is_array($ips)) {
            return null;
        }

        $candidates = [];

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            if (str_starts_with($ip, '127.')) {
                continue;
            }

            if (str_starts_with($ip, '192.168.')) {
                return $ip;
            }

            $candidates[] = $ip;
        }

        foreach ($candidates as $ip) {
            if (str_starts_with($ip, '10.')) {
                return $ip;
            }
        }

        return $candidates[0] ?? null;
    }
}
