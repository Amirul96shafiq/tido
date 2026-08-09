<?php

declare(strict_types=1);

use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance' => 'tido',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);
});

test('sends document parsed message when expense is parsed', function () {
    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Cash']);

    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'whatsapp_sender' => '601116330705',
        'merchant_name' => 'Luckin Coffee',
        'total_amount' => 4.23,
        'payment_method_id' => $paymentMethod->id,
    ]);

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('601116330705'));

    (new SendWhatsAppDocumentParsedJob($expense->id))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'Document parsed')
            && str_contains($text, 'Luckin Coffee')
            && ! str_contains($text, 'Document needs review');
    });
});

test('sends document needs review message when expense requires manual review', function () {
    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Other']);

    $expense = Expense::factory()->create([
        'status' => 'requires_manual_review',
        'source' => 'whatsapp',
        'whatsapp_sender' => '601116330705',
        'merchant_name' => 'Luckin Coffee',
        'total_amount' => 4.23,
        'payment_method_id' => $paymentMethod->id,
    ]);

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('601116330705'));

    (new SendWhatsAppDocumentParsedJob($expense->id))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'Document needs review')
            && str_contains($text, 'Luckin Coffee')
            && str_contains($text, 'Please review and confirm');
    });
});
