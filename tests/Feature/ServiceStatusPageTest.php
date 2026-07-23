<?php

declare(strict_types=1);

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\ServiceStatusPage;
use App\Models\ServiceHealthSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
    ]);

    $this->actingAs(User::factory()->create());
});

test('service status page renders summary banner and uptime labels', function (): void {
    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now(),
        'meta' => ['message' => 'Healthy'],
    ]);

    Livewire::test(ServiceStatusPage::class)
        ->assertSee('Summary report')
        ->assertSee('System status')
        ->assertSee('Monitored services')
        ->assertSee('Ollama')
        ->assertSee('uptime')
        ->assertSee('grid-cols-3', false)
        ->assertSee('x-tooltip', false);
});

test('service status page run check now records samples', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    expect(ServiceHealthSample::query()->count())->toBe(0);

    Livewire::test(ServiceStatusPage::class)
        ->callAction('runCheck')
        ->assertNotified();

    expect(ServiceHealthSample::query()->count())->toBeGreaterThan(0);
});

test('service status page is available in tools navigation', function (): void {
    $this->get(ServiceStatusPage::getUrl())
        ->assertSuccessful();
});
