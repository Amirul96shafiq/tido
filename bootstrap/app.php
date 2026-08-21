<?php

use App\Http\Middleware\EnsureWhatsAppWebhookBodySize;
use App\Http\Middleware\EnsureWhatsAppWebhookSource;
use App\Http\Middleware\SetUserPreferences;
use App\Support\ApplicationStoragePath;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'whatsapp-webhook-body' => EnsureWhatsAppWebhookBodySize::class,
            'whatsapp-webhook-source' => EnsureWhatsAppWebhookSource::class,
        ]);

        $middleware->web(append: [
            SetUserPreferences::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            $path = $request->getPathInfo();

            if (str_contains($path, '/email-change-verification/verify/')) {
                return new RedirectResponse(route('filament.admin.auth.email-change-verification.expired'));
            }

            if (str_contains($path, '/password-reset/reset')) {
                return new RedirectResponse(route('filament.admin.auth.password-reset.expired'));
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if ($request->routeIs('filament.admin.auth.not-found')) {
                return null;
            }

            return new RedirectResponse(route('filament.admin.auth.not-found'));
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if ($request->routeIs('filament.admin.auth.forbidden')) {
                return null;
            }

            return new RedirectResponse(route('filament.admin.auth.forbidden'));
        });
    })->create();

$app->afterLoadingEnvironment(function (Application $app): void {
    ApplicationStoragePath::applyFromEnvironment($app);
});

return $app;
