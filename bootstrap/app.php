<?php

use App\Http\Middleware\SetUserPreferences;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->web(append: [
            SetUserPreferences::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            $path = $request->getPathInfo();

            if (str_contains($path, '/email-change-verification/verify/')) {
                return redirect()->route('filament.admin.auth.email-change-verification.expired');
            }

            if (str_contains($path, '/password-reset/reset')) {
                return redirect()->route('filament.admin.auth.password-reset.expired');
            }
        });
    })->create();
