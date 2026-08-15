<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Foundation\Application;

final class ApplicationStoragePath
{
    public static function applyFromEnvironment(Application $app): void
    {
        $path = env('APP_STORAGE_PATH');

        if (! is_string($path) || $path === '') {
            return;
        }

        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = $app->basePath($path);
        }

        $app->useStoragePath($path);
    }
}
