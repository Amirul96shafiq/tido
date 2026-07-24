<?php

declare(strict_types=1);

namespace App\Support;

final readonly class UserAgentDevice
{
    public function __construct(
        public string $deviceClass,
        public string $browser,
        public string $os,
    ) {}

    public static function parse(?string $userAgent): self
    {
        $userAgent ??= '';

        $isMobile = preg_match(
            '/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i',
            $userAgent,
        ) === 1;

        return new self(
            deviceClass: $isMobile ? 'Mobile Web' : 'Web',
            browser: self::detectBrowser($userAgent),
            os: self::detectOs($userAgent),
        );
    }

    public function detail(?string $ipAddress): string
    {
        $browserLabel = $this->browser !== 'Unknown browser'
            ? "{$this->browser} on {$this->os}"
            : $this->os;

        $parts = array_filter([$browserLabel, $ipAddress]);

        return implode(' · ', $parts);
    }

    private static function detectBrowser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Edg/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    private static function detectOs(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') || str_contains($userAgent, 'iPod') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows NT 10.0') => 'Windows',
            str_contains($userAgent, 'Windows NT 6.3') => 'Windows 8.1',
            str_contains($userAgent, 'Windows NT 6.2') => 'Windows 8',
            str_contains($userAgent, 'Windows NT 6.1') => 'Windows 7',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
    }
}
