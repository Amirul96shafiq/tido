<?php

declare(strict_types=1);

use App\Http\Middleware\LogLivewireUpdates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

test('registers the logging middleware on the livewire update route', function () {
    $route = Route::getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('livewire/update')
        ->and(app('livewire')->getUpdateUri())->toBe('/livewire/update')
        ->and($route->gatherMiddleware())->toContain(LogLivewireUpdates::class);
});

test('logs each livewire component update with its name and actions', function () {
    Log::spy();

    $request = Request::create('/livewire/update', 'POST', [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'filament.pages.dashboard',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updates' => [
                    'filters.month' => '2026-08',
                ],
                'calls' => [
                    ['method' => 'updateChartData'],
                    ['method' => 'updateChartData'],
                ],
            ],
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'filament.widgets.recent-receipts',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [],
            ],
        ],
    ]);

    $response = (new LogLivewireUpdates)->handle(
        $request,
        fn (Request $request): Response => response('ok'),
    );

    expect($response->getStatusCode())->toBe(200);

    Log::shouldHaveReceived('info')
        ->twice()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Livewire component updated'
                && in_array($context['component'], [
                    'filament.pages.dashboard',
                    'filament.widgets.recent-receipts',
                ], true)
                && ($context['actions'] === ['updateChartData']
                    || $context['actions'] === [])
                && is_int($context['updated_properties'])
                && is_int($context['duration_ms'])
                && ! array_key_exists('snapshot', $context);
        });
});
