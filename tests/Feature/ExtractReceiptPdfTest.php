<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('extract receipt data job parses PDF pages then merges them before saving', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/expense.pdf', "%PDF-1.7\ntest expense");

    config([
        'services.documents.pdfinfo_binary' => 'pdfinfo',
        'services.documents.pdftocairo_binary' => 'pdftocairo',
        'services.documents.pdftotext_binary' => 'pdftotext',
        'services.ollama.host' => 'http://ollama.test',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.retry_delays' => [0, 0],
    ]);

    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) {
        if (is_array($process->command) && $process->command[0] === 'pdfinfo') {
            return Process::result(output: "Pages: 2\n");
        }

        if (is_array($process->command) && $process->command[0] === 'pdftotext') {
            return Process::result(output: "Subtotal \$20.00\nTotal \$10.00\nCharged RM42.40 using 1 USD = 4.2397 MYR\n");
        }

        $outputPrefix = $process->command[array_key_last($process->command)];
        File::put($outputPrefix.'-1.jpg', 'page-one-image');
        File::put($outputPrefix.'-2.jpg', 'page-two-image');

        return Process::result();
    });

    $pageOne = [
        'merchant_name' => 'PDF Store',
        'invoice_number' => 'PDF-100',
        'date_time' => '2026-08-01 10:30:00',
        'subtotal' => 10,
        'total_tax' => 0,
        'discount_total' => 0,
        'rounding_amount' => 0,
        'total_amount' => 0,
        'currency' => 'MYR',
        'payment_method' => null,
        'items' => [[
            'description' => 'First item',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
            'serial_number' => null,
            'label' => 'Food & Dining',
        ]],
    ];
    $pageTwo = [
        'merchant_name' => null,
        'invoice_number' => null,
        'date_time' => null,
        'subtotal' => 10,
        'total_tax' => 0,
        'discount_total' => 0,
        'rounding_amount' => 0,
        'total_amount' => 10,
        'currency' => null,
        'payment_method' => 'Cash',
        'items' => [],
    ];
    $merged = array_replace($pageOne, [
        'total_amount' => 10,
        'payment_method' => 'Cash',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://ollama.test/api/generate' => Http::sequence()
            ->push(['response' => json_encode($pageOne)])
            ->push(['response' => json_encode($pageTwo)])
            ->push(['response' => json_encode($merged)]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0,
        'total_tax' => 0,
        'total_amount' => 0,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/expense.pdf',
        'original_filename' => 'expense.pdf',
        'file_mime_type' => 'application/pdf',
        'file_page_count' => 2,
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    $expense->refresh();

    expect($expense->status)->toBe('parsed')
        ->and($expense->merchant_name)->toBe('PDF Store')
        ->and($expense->currency)->toBe('MYR')
        ->and($expense->original_currency)->toBe('USD')
        ->and($expense->total_amount)->toBe('42.40')
        ->and($expense->raw_ai_response['currency_detection'])->toBe([
            'currency' => 'USD',
            'source' => 'document_text',
            'rate' => 4.2397,
            'rate_source' => 'printed_receipt_rate',
        ])
        ->and($expense->expenseItems)->toHaveCount(1)
        ->and($expense->expenseItems->first()->description)->toBe('First item')
        ->and($expense->expenseItems->first()->line_total)->toBe('42.40');

    expect(collect(Http::recorded())->filter(
        fn (array $record): bool => str_contains($record[0]->url(), 'currencyapi.test'),
    ))->toHaveCount(0);

    $ollamaRequests = collect(Http::recorded())
        ->map(fn (array $record): Request => $record[0])
        ->filter(fn (Request $request): bool => $request->url() === 'http://ollama.test/api/generate')
        ->values();

    expect($ollamaRequests)->toHaveCount(3)
        ->and($ollamaRequests[0]['images'])->toBe([base64_encode('page-one-image')])
        ->and($ollamaRequests[1]['images'])->toBe([base64_encode('page-two-image')])
        ->and($ollamaRequests[2]['images'] ?? null)->toBeNull()
        ->and((string) $ollamaRequests[2]['prompt'])->toContain('Merge the page-level JSON');
});
