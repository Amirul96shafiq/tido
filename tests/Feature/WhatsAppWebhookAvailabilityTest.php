<?php

declare(strict_types=1);

use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_allowed_ips' => '127.0.0.1,::1',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.timeout' => 15,
        'services.evolution.connect_timeout' => 5,
    ]);

    Cache::flush();
    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');

    User::factory()->create(['phone' => '60123456789']);
});

test('webhook text path never calls evolution while the queue is faked', function (): void {
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $this->postJson('/api/webhooks/whatsapp', [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-AVAIL-1',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ], evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();
    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, 1);
});

test('text reply job sends the expected help body', function (): void {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-AVAIL-HELP');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*')
            && str_contains((string) $request['text'], '— Powered by *tido*');
    });
});

test('text reply job sends the expected spend body', function (): void {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'How much did I spend this month?', 'MSG-AVAIL-SPEND');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Monthly Spending')
            && str_contains((string) $request['text'], 'Total spent:');
    });
});

test('text reply job declares evolution-send rate limited middleware', function (): void {
    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-AVAIL-HELP');
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(2)
        ->and(collect($middleware)->contains(fn ($item): bool => $item instanceof RateLimited))->toBeTrue();

    $limiterName = collect($middleware)
        ->first(fn ($item): bool => $item instanceof RateLimited);

    expect($limiterName)->toBeInstanceOf(RateLimited::class);

    $limiterName = (new ReflectionProperty($limiterName, 'limiterName'))->getValue($limiterName);

    expect($limiterName)->toBe('evolution-send');
});

test('text reply job timeout follows evolution timeout config', function (): void {
    config(['services.evolution.timeout' => 7]);

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-AVAIL-HELP');

    expect($job->timeout)->toBe(22)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30]);
});

test('notification service client uses configured connect and total timeouts', function (): void {
    config([
        'services.evolution.timeout' => 7,
        'services.evolution.connect_timeout' => 2,
    ]);

    $service = app(WhatsAppNotificationService::class);
    $clientMethod = new ReflectionMethod($service, 'client');
    $pending = $clientMethod->invoke($service);
    $options = (new ReflectionProperty($pending, 'options'))->getValue($pending);

    expect($options['timeout'] ?? null)->toBe(7)
        ->and($options['connect_timeout'] ?? null)->toBe(2);
});
