<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ProductionEnvironmentBaseline
{
    public const UNAVAILABLE_MESSAGE = 'Production environment baseline is not configured.';

    public static function assert(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('app.debug') !== false) {
            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
        }

        if (config('session.secure') !== true) {
            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
        }

        if (config('session.http_only') !== true) {
            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
        }

        $sameSite = config('session.same_site');

        if (! is_string($sameSite) || ! in_array(strtolower($sameSite), ['lax', 'strict'], true)) {
            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
        }
    }
}
