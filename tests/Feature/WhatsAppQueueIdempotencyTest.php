<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\ParseManualWhatsAppExpenseJob;
use App\Jobs\ProcessManualWhatsAppExpenseJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\OllamaService;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppProcessingJobKey;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PaymentMethodSeeder::class);

    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.timeout' => 15,
        'services.evolution.connect_timeout' => 5,
        'services.documents.max_bytes' => 10 * 1024 * 1024,
    ]);

    User::factory()->create(['phone' => '60123456789']);

    Cache::flush();
    RateLimiter::clear('evolution-send');
    RateLimiter::clear('ollama-generate');
});

test('duplicate process whatsapp media job dispatch is suppressed by unique lock', function (): void {
    Queue::fake();

    $args = [
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-UNIQUE-MEDIA',
        false,
    ];

    ProcessWhatsAppMediaJob::dispatch(...$args);
    ProcessWhatsAppMediaJob::dispatch(...$args);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, 1);
});

test('manual whatsapp expense job handle twice creates only one expense', function (): void {
    Queue::fake();

    $text = "myNEWS Bayu Residensi;\nGARDENIA QUICKBITES CREAM ROLL, 1, 1.2;";

    $job = new ProcessManualWhatsAppExpenseJob('60123456789', $text, 'MSG-MANUAL-DUP');

    $job->handle();
    $job->handle();

    expect(Expense::count())->toBe(1)
        ->and(Expense::query()->value('whatsapp_message_id'))->toBe('MSG-MANUAL-DUP');
});

test('manual whatsapp expense job assigns message ids for multiple blocks', function (): void {
    Queue::fake();

    $text = <<<'TEXT'
myNEWS Bayu Residensi;
GARDENIA QUICKBITES CREAM ROLL, 1, 1.2;

7-Eleven Malaysia Sdn. Bhd.;
Hausboom Grapple 325, 1, 2;
TEXT;

    $job = new ProcessManualWhatsAppExpenseJob('60123456789', $text, 'MSG-MULTI');

    $job->handle();
    $job->handle();

    expect(Expense::count())->toBe(2);

    $messageIds = Expense::query()
        ->orderBy('id')
        ->pluck('whatsapp_message_id')
        ->all();

    expect($messageIds)->toBe(['MSG-MULTI', 'MSG-MULTI:1']);
});

test('text reply job handle twice sends only one evolution message', function (): void {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-REPLY-DUP');

    $job->handle(app(WhatsAppNotificationService::class));
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSentCount(1);
    expect(Cache::has(WhatsAppProcessingJobKey::textReplySentCacheKey('MSG-REPLY-DUP')))->toBeTrue();
});

test('media job timeout follows evolution timeout config', function (): void {
    config([
        'services.evolution.timeout' => 11,
        'services.evolution.connect_timeout' => 4,
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-TIMEOUT',
        false,
    );

    expect($job->timeout)->toBe(135);
});

test('media download completes with configured evolution client', function (): void {
    Storage::fake('local');
    Queue::fake();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-DOWNLOAD',
        false,
    );

    app()->call([$job, 'handle']);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/chat/getBase64FromMediaMessage/');
    });

    expect(Expense::query()->where('whatsapp_message_id', 'MSG-DOWNLOAD')->exists())->toBeTrue();
});

test('processing jobs declare expected queue middleware', function (): void {
    $mediaJob = new ProcessWhatsAppMediaJob('60123456789', '60123456789@s.whatsapp.net', 'MSG-MW', false);
    $mediaMiddleware = $mediaJob->middleware();

    expect(collect($mediaMiddleware)->contains(fn ($middleware): bool => $middleware instanceof WithoutOverlapping))->toBeTrue()
        ->and(collect($mediaMiddleware)->contains(fn ($middleware): bool => $middleware instanceof RateLimited))->toBeTrue();

    $extractJob = new ExtractReceiptDataJob(1);
    $extractMiddleware = $extractJob->middleware();

    expect(collect($extractMiddleware)->contains(fn ($middleware): bool => $middleware instanceof RateLimited))->toBeTrue();

    $parseJob = new ParseManualWhatsAppExpenseJob(1);
    $parseMiddleware = $parseJob->middleware();

    expect(collect($parseMiddleware)->contains(fn ($middleware): bool => $middleware instanceof RateLimited))->toBeTrue();

    $replyJob = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-MW-REPLY');
    $replyMiddleware = $replyJob->middleware();

    expect($replyMiddleware)->toHaveCount(2)
        ->and(collect($replyMiddleware)->contains(fn ($middleware): bool => $middleware instanceof WithoutOverlapping))->toBeTrue()
        ->and(collect($replyMiddleware)->contains(fn ($middleware): bool => $middleware instanceof RateLimited))->toBeTrue();
});

test('parse manual whatsapp expense job failed sets requires manual review', function (): void {
    Queue::fake();

    $expense = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'image_path' => null,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);

    $job = new ParseManualWhatsAppExpenseJob($expense->id);
    $job->failed(new RuntimeException('Ollama unavailable'));

    expect($expense->fresh()->status)->toBe('requires_manual_review');
});

test('extract receipt data job failed sets requires manual review', function (): void {
    Queue::fake();

    $expense = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'image_path' => 'receipts/test.jpg',
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);

    Storage::fake('local');
    Storage::put('receipts/test.jpg', 'image');

    $job = new ExtractReceiptDataJob($expense->id);
    $job->failed(new RuntimeException('Ollama unavailable'));

    expect($expense->fresh()->status)->toBe('requires_manual_review');
});

test('ollama service generate json uses connect timeout config', function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'test-model',
        'services.ollama.timeout' => 30,
        'services.ollama.connect_timeout' => 4,
    ]);

    Http::fake([
        'http://ollama.test/api/generate' => Http::response([
            'response' => json_encode(['items' => []]),
        ]),
    ]);

    app(OllamaService::class)->generateJson('{"prompt":"test"}');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/generate');
    });
});
