<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\ServiceStatusPage;
use App\Models\ServiceHealthSample;
use App\Models\User;
use App\Services\Health\ServiceHealthAlertService;
use App\Services\Health\ServiceHealthRecorder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance_name' => 'tido',
        'broadcasting.default' => 'null',
    ]);

    $this->actingAs(User::factory()->create(['notify_service_status' => true]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * @param  array<string, ServiceHealthStatus>  $statuses
 * @return array<string, ServiceHealthSample>
 */
function seedPreviousHealthSamples(array $statuses): array
{
    $checkedAt = now()->subMinutes(15);
    $previous = [];

    foreach ($statuses as $serviceValue => $status) {
        $service = MonitoredService::from($serviceValue);
        $previous[$service->value] = ServiceHealthSample::query()->create([
            'service' => $service,
            'status' => $status,
            'checked_at' => $checkedAt,
            'latency_ms' => 10,
            'meta' => ['message' => 'Seeded sample.'],
        ]);
    }

    return $previous;
}

/**
 * @param  array<string, ServiceHealthStatus>  $statuses
 * @return list<ServiceHealthSample>
 */
function makeNewHealthSamples(array $statuses): array
{
    $checkedAt = now();
    $samples = [];

    foreach ($statuses as $serviceValue => $status) {
        $samples[] = ServiceHealthSample::query()->create([
            'service' => MonitoredService::from($serviceValue),
            'status' => $status,
            'checked_at' => $checkedAt,
            'latency_ms' => 12,
            'meta' => ['message' => 'New sample.'],
        ]);
    }

    return $samples;
}

test('first health probe does not send an inbox alert', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => [['name' => 'qwen2.5vl:7b']]]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    app(ServiceHealthRecorder::class)->recordAll();

    expect(auth()->user()->fresh()->notifications()->count())->toBe(0);
});

test('operational to down transition sends a danger inbox alert', function (): void {
    $user = auth()->user();

    $previous = seedPreviousHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Operational,
    ]);
    $newSamples = makeNewHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);

    app(ServiceHealthAlertService::class)->notifyTransitions($previous, $newSamples);

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();

    expect($notification->data['title'])->toBe('Service Status down')
        ->and($notification->data['body'])->toContain('Ollama is down.')
        ->and($notification->data['actions'][0]['url'])->toBe(ServiceStatusPage::getUrl());
});

test('unchanged down status does not send a second inbox alert', function (): void {
    $user = auth()->user();

    $previous = seedPreviousHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);
    $newSamples = makeNewHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);

    app(ServiceHealthAlertService::class)->notifyTransitions($previous, $newSamples);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('recovery to operational sends a success inbox alert', function (): void {
    $user = auth()->user();

    $previous = seedPreviousHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);
    $newSamples = makeNewHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Operational,
    ]);

    app(ServiceHealthAlertService::class)->notifyTransitions($previous, $newSamples);

    $user->refresh();

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['title'])->toBe('Service Status recovered')
        ->and($user->notifications()->first()->data['body'])->toContain('Ollama recovered to operational.');
});

test('service status inbox alerts are skipped when the preference is off', function (): void {
    $user = auth()->user();
    $user->update(['notify_service_status' => false]);

    $previous = seedPreviousHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Operational,
    ]);
    $newSamples = makeNewHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);

    app(ServiceHealthAlertService::class)->notifyTransitions($previous, $newSamples);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('family member login users do not receive service status inbox alerts', function (): void {
    $primary = auth()->user();
    $family = User::factory()->create([
        'household_role' => HouseholdRole::FamilyMember,
        'notify_service_status' => true,
    ]);

    $previous = seedPreviousHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Operational,
    ]);
    $newSamples = makeNewHealthSamples([
        MonitoredService::Ollama->value => ServiceHealthStatus::Down,
    ]);

    app(ServiceHealthAlertService::class)->notifyTransitions($previous, $newSamples);

    expect($primary->fresh()->notifications()->count())->toBe(1)
        ->and($family->fresh()->notifications()->count())->toBe(0);
});
