<?php

declare(strict_types=1);

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Models\ServiceHealthSample;
use App\Services\Health\Probes\OllamaProbe;
use App\Services\Health\Probes\ReverbProbe;
use App\Services\Health\ServiceHealthAggregator;
use App\Services\Health\ServiceHealthRecorder;
use Carbon\Carbon;
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
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('ollama probe returns operational when tags endpoint responds', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => [['name' => 'qwen2.5vl:7b']]]),
    ]);

    $result = app(OllamaProbe::class)->probe();

    expect($result->status)->toBe(ServiceHealthStatus::Operational)
        ->and($result->message())->toBe('1 model(s) available: qwen2.5vl:7b.');
});

test('ollama probe lists multiple model names when present', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response([
            'models' => [
                ['name' => 'qwen2.5vl:7b'],
                ['name' => 'llama3.2:latest'],
            ],
        ]),
    ]);

    $result = app(OllamaProbe::class)->probe();

    expect($result->status)->toBe(ServiceHealthStatus::Operational)
        ->and($result->message())->toBe('2 model(s) available: qwen2.5vl:7b, llama3.2:latest.');
});

test('ollama probe returns down when tags endpoint fails', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response([], 500),
    ]);

    $result = app(OllamaProbe::class)->probe();

    expect($result->status)->toBe(ServiceHealthStatus::Down);
});

test('health recorder stores one sample per configured service', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    $samples = app(ServiceHealthRecorder::class)->recordAll();

    expect($samples)->not->toBeEmpty()
        ->and(ServiceHealthSample::query()->count())->toBe(count($samples));

    $services = ServiceHealthSample::query()
        ->pluck('service')
        ->map(static fn (MonitoredService $service): string => $service->value)
        ->all();

    expect($services)->toContain(MonitoredService::App->value)
        ->and($services)->toContain(MonitoredService::Database->value)
        ->and($services)->toContain(MonitoredService::Ollama->value)
        ->and($services)->toContain(MonitoredService::Evolution->value)
        ->and($services)->toContain(MonitoredService::Queue->value)
        ->and($services)->not->toContain(MonitoredService::Reverb->value);
});

test('aggregator groups samples into twelve hour pieces and calculates uptime', function (): void {
    $timezone = 'Asia/Kuala_Lumpur';
    Carbon::setTestNow(Carbon::parse('2026-08-15 15:00:00', $timezone));
    $day = now($timezone)->startOfDay();

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => $day->copy()->addHours(1),
        'latency_ms' => 10,
        'meta' => ['message' => 'Healthy morning sample.'],
    ]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Down,
        'checked_at' => $day->copy()->addHours(13),
        'latency_ms' => 20,
        'meta' => ['message' => 'Afternoon outage.'],
    ]);

    $report = app(ServiceHealthAggregator::class)->report($timezone);
    $ollama = collect($report['services'])->firstWhere(
        static fn (array $service): bool => $service['service'] === MonitoredService::Ollama,
    );

    expect($ollama)->not->toBeNull()
        ->and($ollama['uptimePercent'])->toBe(50.0)
        ->and($ollama['pieces'])->toHaveCount(ServiceHealthAggregator::VISIBLE_DAYS * ServiceHealthAggregator::PIECES_PER_DAY);

    $todayMorningPiece = collect($ollama['pieces'])->first(
        static fn (array $piece): bool => $piece['startsAt']->isSameDay($day)
            && (int) $piece['startsAt']->format('H') === 0,
    );

    $todayAfternoonPiece = collect($ollama['pieces'])->first(
        static fn (array $piece): bool => $piece['startsAt']->isSameDay($day)
            && (int) $piece['startsAt']->format('H') === 12,
    );

    expect($todayMorningPiece)->not->toBeNull()
        ->and($todayAfternoonPiece)->not->toBeNull()
        ->and($todayMorningPiece['status'])->toBe(ServiceHealthStatus::Operational)
        ->and($todayMorningPiece['detail'])->toBe('Healthy morning sample.')
        ->and($todayMorningPiece['tooltip'])->toContain('Operational')
        ->and($todayMorningPiece['tooltip'])->toContain('<br>')
        ->and($todayAfternoonPiece['status'])->toBe(ServiceHealthStatus::Down)
        ->and($todayAfternoonPiece['detail'])->toBe('Afternoon outage.');
});

test('aggregator uses latest sample for in progress piece instead of worst', function (): void {
    $timezone = 'Asia/Kuala_Lumpur';
    Carbon::setTestNow(Carbon::parse('2026-08-15 15:00:00', $timezone));
    $now = now($timezone);
    $pieceStart = $now->copy()->startOfDay()->addHours(12);
    $pieceEnd = $pieceStart->copy()->addHours(12);

    expect($pieceEnd->isFuture())->toBeTrue();

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Down,
        'checked_at' => $pieceStart->copy()->addHour(),
        'meta' => ['message' => 'Earlier outage.'],
    ]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => $now->copy()->subMinutes(5),
        'meta' => ['message' => 'Recovered.'],
    ]);

    $report = app(ServiceHealthAggregator::class)->report($timezone);
    $ollama = collect($report['services'])->firstWhere(
        static fn (array $service): bool => $service['service'] === MonitoredService::Ollama,
    );

    $currentPiece = collect($ollama['pieces'])->first(
        static fn (array $piece): bool => $piece['startsAt']->equalTo($pieceStart),
    );

    expect($currentPiece['status'])->toBe(ServiceHealthStatus::Operational)
        ->and($currentPiece['detail'])->toBe('Recovered.')
        ->and($currentPiece['status']->barColorClass())->toBe('bg-emerald-500');
});

test('aggregator summary reports fully operational when latest samples are healthy', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    app(ServiceHealthRecorder::class)->recordAll();

    $report = app(ServiceHealthAggregator::class)->report();

    expect($report['summary']['status'])->toBe(ServiceHealthStatus::Operational)
        ->and($report['summary']['title'])->toBe('All services fully operational');
});

test('health prune command deletes old samples', function (): void {
    ServiceHealthSample::query()->create([
        'service' => MonitoredService::App,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now()->subDays(40),
    ]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::App,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now()->subDay(),
    ]);

    $this->artisan('health:prune --days=30')
        ->assertSuccessful();

    expect(ServiceHealthSample::query()->count())->toBe(1);
});

test('reverb probe returns operational when the http api responds', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => 'reverb.test',
        'broadcasting.connections.reverb.options.port' => 8081,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://reverb.test:8081/apps' => Http::response('', 401),
    ]);

    $result = app(ReverbProbe::class)->probe();

    expect($result->status)->toBe(ServiceHealthStatus::Operational)
        ->and($result->message())->toBe('Reverb websocket is reachable.');
});

test('reverb probe returns down when the http api is unreachable', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => 'reverb.test',
        'broadcasting.connections.reverb.options.port' => 8081,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://reverb.test:8081/apps' => Http::failedConnection(),
    ]);

    $result = app(ReverbProbe::class)->probe();

    expect($result->status)->toBe(ServiceHealthStatus::Down)
        ->and($result->message())->toBe('Reverb is unreachable.');
});

test('health recorder stores a reverb sample when broadcasting uses reverb', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => 'reverb.test',
        'broadcasting.connections.reverb.options.port' => 8081,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);

    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        'http://reverb.test:8081/apps' => Http::response('', 401),
    ]);

    $samples = app(ServiceHealthRecorder::class)->recordAll();
    $services = collect($samples)
        ->map(static fn (ServiceHealthSample $sample): string => $sample->service->value)
        ->all();

    expect($services)->toContain(MonitoredService::Reverb->value);
});
