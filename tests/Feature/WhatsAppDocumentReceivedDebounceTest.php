<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\SendWhatsAppDocumentReceivedAckJob;
use App\Models\Expense;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.document_received_debounce_seconds' => 3,
    ]);

    Cache::flush();
});

test('two media jobs for same sender batch into one document received ack then dispatch OCR', function () {
    Storage::fake('local');
    Queue::fake();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    $firstJob = new ProcessWhatsAppMediaJob($sender, $sender.'@s.whatsapp.net', 'MSG-A', false);
    app()->call([$firstJob, 'handle']);

    $this->travel(1)->second();

    $secondJob = new ProcessWhatsAppMediaJob($sender, $sender.'@s.whatsapp.net', 'MSG-B', false);
    app()->call([$secondJob, 'handle']);

    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class, 2);
    Queue::assertNotPushed(ExtractReceiptDataJob::class);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

    expect($payload)->toBeArray()
        ->and($payload['count'])->toBe(2)
        ->and($payload['expense_ids'])->toHaveCount(2)
        ->and($payload['token'])->toBeString();

    $winningToken = $payload['token'];
    $expenseIds = $payload['expense_ids'];

    (new SendWhatsAppDocumentReceivedAckJob($sender, 'stale-token'))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));

    expect(Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender)))->toBeArray();

    (new SendWhatsAppDocumentReceivedAckJob($sender, $winningToken))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Document received*')
            && str_contains((string) $request['text'], 'A total of *2* file(s) saved and queued for AI parsing.');
    });

    expect(Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender)))->toBeNull();

    Queue::assertPushed(ExtractReceiptDataJob::class, 2);
    foreach ($expenseIds as $expenseId) {
        Queue::assertPushed(ExtractReceiptDataJob::class, function (ExtractReceiptDataJob $job) use ($expenseId): bool {
            return $job->expenseId === (int) $expenseId;
        });
    }

    expect(Expense::count())->toBe(2);
});

test('superseded document received ack token is ignored', function () {
    Queue::fake();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-101',
        'expense_id' => 101,
        'filename' => 'one.jpg',
        'mime_type' => 'image/jpeg',
        'page_count' => null,
        'status' => 'accepted',
        'reason' => null,
    ]);
    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-102',
        'expense_id' => 102,
        'filename' => 'two.jpg',
        'mime_type' => 'image/jpeg',
        'page_count' => null,
        'status' => 'accepted',
        'reason' => null,
    ]);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));
    expect($payload['count'])->toBe(2)
        ->and($payload['expense_ids'])->toBe([101, 102]);

    (new SendWhatsAppDocumentReceivedAckJob($sender, 'old-token'))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
    Queue::assertNotPushed(ExtractReceiptDataJob::class);

    expect(Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender)))->toBeArray()
        ->and(Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender))['count'])->toBe(2);
});

test('mixed PDF batch acknowledges rejected files and only dispatches accepted expenses', function () {
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-VALID',
        'expense_id' => 101,
        'filename' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'page_count' => 2,
        'status' => 'accepted',
        'reason' => null,
    ]);
    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-LONG',
        'expense_id' => null,
        'filename' => 'statement.pdf',
        'mime_type' => 'application/pdf',
        'page_count' => 8,
        'status' => 'rejected',
        'reason' => 'pdf_page_limit',
    ]);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

    (new SendWhatsAppDocumentReceivedAckJob($sender, $payload['token']))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'A total of *2* file(s) received.')
            && str_contains($text, '*1* file(s) saved and queued for AI parsing.')
            && str_contains($text, 'statement.pdf - 8 pages (maximum 3)');
    });

    Queue::assertPushed(ExtractReceiptDataJob::class, fn (ExtractReceiptDataJob $job): bool => $job->expenseId === 101);
    Queue::assertPushed(ExtractReceiptDataJob::class, 1);
});

test('document received fallback acknowledges a PDF processing failure after a database lock', function () {
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-VALID',
        'expense_id' => 101,
        'filename' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'page_count' => 2,
        'status' => 'accepted',
        'reason' => null,
    ]);
    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-LOCKED',
        'expense_id' => null,
        'filename' => 'statement.pdf',
        'mime_type' => 'application/pdf',
        'page_count' => null,
        'status' => 'failed',
        'reason' => 'pdf_processing_failed',
    ]);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

    (new SendWhatsAppDocumentReceivedAckJob($sender, $payload['token']))
        ->failed(new RuntimeException('SQLSTATE[HY000]: General error: 5 database is locked'));

    Http::assertSent(function (Request $request): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'A total of *2* file(s) received.')
            && str_contains($text, '*1* file(s) saved and queued for AI parsing.')
            && str_contains($text, '*1* file(s) could not be processed:')
            && str_contains($text, 'statement.pdf - could not be processed; please resend the PDF');
    });

    Queue::assertPushed(ExtractReceiptDataJob::class, fn (ExtractReceiptDataJob $job): bool => $job->expenseId === 101);
});
