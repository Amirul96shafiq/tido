<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('whatsapp webhook rejects unauthorized requests', function () {
    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => 'Bearer invalid-token',
    ])->assertStatus(401);
});

test('whatsapp webhook handles text queries for monthly spent', function () {
    $expectedToken = (string) config('services.evolution.api_key');

    Invoice::factory()->count(3)->create([
        'total_amount' => 50.00,
        'date_time' => now(),
        'status' => 'reviewed',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG123',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'How much did I spend this month?',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'Authorization' => 'Bearer ' . $expectedToken,
    ])->assertSuccessful();

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Your total spending for this month');
    });
});

test('whatsapp webhook imports image message and triggers queue', function () {
    Storage::fake('local');
    Queue::fake();

    $expectedToken = (string) config('services.evolution.api_key');

    Http::fake([
        '*/chat/retreiveMedia/*' => Http::response([
            'base64' => base64_encode('fake-receipt-binary-image'),
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG456',
            ],
            'messageType' => 'imageMessage',
            'message' => [
                'imageMessage' => [],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'Authorization' => 'Bearer ' . $expectedToken,
    ])->assertSuccessful();

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull();
    expect($invoice->source)->toBe('whatsapp');
    expect(Storage::exists($invoice->image_path))->toBeTrue();

    Queue::assertPushed(ExtractReceiptDataJob::class);
});
