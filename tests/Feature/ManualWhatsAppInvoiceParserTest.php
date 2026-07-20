<?php

declare(strict_types=1);

use App\Models\PaymentMethod;
use App\Support\ManualWhatsAppInvoiceParser;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PaymentMethodSeeder::class);
});

test('parser extracts single merchant block with items', function () {
    $text = <<<'TEXT'
myNEWS Bayu Residensi;
Kopi O, 1, 2.50;
Roti, 2, 3.00;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['merchant_name'])->toBe('myNEWS Bayu Residensi')
        ->and($blocks[0]['payment_method']->slug)->toBe('cash')
        ->and($blocks[0]['items'])->toHaveCount(2)
        ->and($blocks[0]['items'][0])->toMatchArray([
            'description' => 'Kopi O',
            'quantity' => 1.0,
            'line_total' => 2.5,
        ]);
});

test('parser defaults payment method to cash when token omitted', function () {
    $text = <<<'TEXT'
7-Eleven;
Item A, 1, 1.00;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['payment_method']->slug)->toBe('cash');
});

test('parser maps merchant payment tokens', function (string $token, string $expectedSlug) {
    $text = <<<TEXT
Store Name, {$token};
Item A, 1, 1.00;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['payment_method']->slug)->toBe($expectedSlug);
})->with([
    'qr' => ['qr', 'pay_with_qr'],
    'tngo' => ['tngo', 'touchngo'],
    'card' => ['card', 'mastercard'],
    'cash' => ['cash', 'cash'],
    'visa' => ['visa', 'visa'],
]);

test('unknown trailing token stays part of merchant name', function () {
    $text = <<<'TEXT'
Store Name, unknownToken;
Item A, 1, 1.00;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['merchant_name'])->toBe('Store Name, unknownToken')
        ->and($blocks[0]['payment_method']->slug)->toBe('cash');
});

test('parser resolves custom payment method aliases', function () {
    PaymentMethod::factory()->create([
        'name' => 'GrabPay',
        'slug' => 'grabpay',
        'aliases' => ['grab'],
    ]);

    $text = <<<'TEXT'
Food Court, grab;
Nasi Lemak, 1, 8.50;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['payment_method']->slug)->toBe('grabpay');
});
