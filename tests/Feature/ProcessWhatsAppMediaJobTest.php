<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\SendWhatsAppDocumentReceivedAckJob;
use App\Models\Expense;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.documents.max_bytes' => 10 * 1024 * 1024,
        'services.documents.max_pdf_pages' => 3,
    ]);

    Cache::flush();
});

test('process whatsapp media job stores receipt and schedules batched document received ack', function () {
    Storage::fake('local');
    Queue::fake();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG456',
        false,
    );

    app()->call([$job, 'handle']);

    $expense = Expense::first();
    expect($expense)->not->toBeNull()
        ->and($expense->source)->toBe('whatsapp')
        ->and($expense->whatsapp_sender)->toBe('60123456789')
        ->and($expense->whatsapp_message_id)->toBe('MSG456')
        ->and($expense->file_mime_type)->toBe('image/png')
        ->and(Storage::exists($expense->image_path))->toBeTrue();

    Queue::assertNotPushed(ExtractReceiptDataJob::class);
    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class, function (SendWhatsAppDocumentReceivedAckJob $ack): bool {
        return $ack->senderNumber === '60123456789'
            && $ack->token !== '';
    });

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/chat/getBase64FromMediaMessage/')
            && ($request['message']['key']['id'] ?? null) === 'MSG456'
            && ($request['convertToMp4'] ?? null) === false;
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
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

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'Failed to download WhatsApp receipt media.');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Upload failed (attempt 1 of 3)*')
            && str_contains((string) $request['text'], 'Automatic retry in about 60 seconds');
    });

    expect(Expense::count())->toBe(0);
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

    expect(fn () => app()->call([$job, 'handle']))
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

    app()->call([$job, 'handle']);

    Http::assertNothingSent();
    expect(Expense::count())->toBe(0);
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

test('process whatsapp media job stores an accepted PDF with document metadata', function () {
    Storage::fake('local');
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake(['*' => Process::result(output: "Pages: 2\n")]);

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode("%PDF-1.7\nreceipt"),
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PDF-2',
        false,
        'pdf',
        'application/pdf',
        'shop receipt.pdf',
    );

    app()->call([$job, 'handle']);

    $expense = Expense::sole();

    expect($expense->original_filename)->toBe('shop receipt.pdf')
        ->and($expense->whatsapp_message_id)->toBe('MSG-PDF-2')
        ->and($expense->file_mime_type)->toBe('application/pdf')
        ->and($expense->file_page_count)->toBe(2)
        ->and($expense->image_path)->toEndWith('.pdf')
        ->and(Storage::exists($expense->image_path))->toBeTrue();

    Queue::assertNotPushed(ExtractReceiptDataJob::class);
    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class);
});

test('process whatsapp media job rejects a PDF over three pages before creating an expense', function () {
    Storage::fake('local');
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake(['*' => Process::result(output: "Pages: 4\n")]);

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode("%PDF-1.7\nlong receipt"),
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PDF-4',
        false,
        'pdf',
        'application/pdf',
        'statement.pdf',
    );

    app()->call([$job, 'handle']);

    $batch = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));

    expect(Expense::count())->toBe(0)
        ->and(Storage::allFiles('receipts'))->toBe([])
        ->and($batch['count'])->toBe(1)
        ->and($batch['expense_ids'])->toBe([])
        ->and($batch['documents'][0]['status'])->toBe('rejected')
        ->and($batch['documents'][0]['reason'])->toBe('pdf_page_limit')
        ->and($batch['documents'][0]['page_count'])->toBe(4);

    Queue::assertNotPushed(ExtractReceiptDataJob::class);
    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class);
});

test('process whatsapp media job queues a PDF when the inspection utility path is invalid', function () {
    Storage::fake('local');
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            errorOutput: 'The filename, directory name, or volume label syntax is incorrect.',
            exitCode: 1,
        ),
    ]);

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode("%PDF-1.7\nreceipt"),
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PDF-MISSING-UTILITY',
        false,
        'pdf',
        'application/pdf',
        'receipt.pdf',
    );

    app()->call([$job, 'handle']);

    $expense = Expense::sole();

    expect($expense->file_page_count)->toBeNull()
        ->and($expense->file_mime_type)->toBe('application/pdf')
        ->and(Storage::exists($expense->image_path))->toBeTrue();

    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class);
});

test('process whatsapp PDF job records a fallback acknowledgement after a database lock failure', function () {
    Storage::fake('local');
    Queue::fake();

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PDF-LOCKED',
        false,
        'pdf',
        'application/pdf',
        'statement.pdf',
    );

    $job->failed(new RuntimeException('SQLSTATE[HY000]: General error: 5 database is locked (SQL: update "jobs" set "reserved_at" = 1 where "id" = 1)'));

    $batch = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));

    expect($batch['count'])->toBe(1)
        ->and($batch['documents'][0]['status'])->toBe('failed')
        ->and($batch['documents'][0]['reason'])->toBe('pdf_processing_failed')
        ->and($batch['documents'][0]['filename'])->toBe('statement.pdf');

    Queue::assertPushed(SendWhatsAppDocumentReceivedAckJob::class);
});
