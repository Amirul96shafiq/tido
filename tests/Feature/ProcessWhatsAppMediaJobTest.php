<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Models\Invoice;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);
});

test('process whatsapp media job stores receipt and sends success message', function () {
    Storage::fake('local');
    Queue::fake();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode('fake-receipt-binary-image'),
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG456',
        false,
    );

    $job->handle(app(WhatsAppNotificationService::class));

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->source)->toBe('whatsapp')
        ->and(Storage::exists($invoice->image_path))->toBeTrue();

    Queue::assertPushed(ExtractReceiptDataJob::class);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/chat/getBase64FromMediaMessage/')
            && ($request['message']['key']['id'] ?? null) === 'MSG456'
            && ($request['convertToMp4'] ?? null) === false;
    });

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Document received*');
    });
});

test('process whatsapp media job sends attempt 1 failure message and throws', function () {
    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response('error', 500),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-FAIL-1',
        false,
    );

    expect(fn () => $job->handle(app(WhatsAppNotificationService::class)))
        ->toThrow(RuntimeException::class, 'Failed to download WhatsApp receipt media.');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Upload failed (attempt 1 of 3)*')
            && str_contains((string) $request['text'], 'Automatic retry in about 60 seconds');
    });

    expect(Invoice::count())->toBe(0);
});

test('process whatsapp media job sends final attempt failure message', function () {
    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response('error', 500),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new class('60123456789', '60123456789@s.whatsapp.net', 'MSG-FAIL-3', false) extends ProcessWhatsAppMediaJob
    {
        protected function attemptNumber(): int
        {
            return 3;
        }
    };

    expect(fn () => $job->handle(app(WhatsAppNotificationService::class)))
        ->toThrow(RuntimeException::class);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Upload failed (attempt 3 of 3)*')
            && str_contains((string) $request['text'], 'final attempt')
            && str_contains((string) $request['text'], 'Resend the document to try again.');
    });
});

test('process whatsapp media job skips duplicate message processing', function () {
    Storage::fake('local');
    Queue::fake();

    $filename = 'wa_MSG-DUP.jpg';
    Storage::put('receipts/'.$filename, 'existing-image');

    Http::fake();

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-DUP',
        false,
    );

    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
    expect(Invoice::count())->toBe(0);
});

test('process whatsapp media job retries three times with 60 second backoff', function () {
    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-QUEUE',
        true,
    );

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 60])
        ->and($job->fromMe)->toBeTrue();
});
