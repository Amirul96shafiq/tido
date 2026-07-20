<?php

declare(strict_types=1);

use App\Support\ManualWhatsAppInvoiceParser;

test('parser accepts a single merchant with line items', function () {
    $text = <<<'TEXT'
myNEWS Bayu Residensi;
GARDENIA QUICKBITES CREAM ROLL, 1, 1.2;
GARDENIA ORIG CLASSIC ENR.WHIT, 1, 3;
TEXT;

    expect(ManualWhatsAppInvoiceParser::looksLike($text))->toBeTrue();

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['merchant_name'])->toBe('myNEWS Bayu Residensi')
        ->and($blocks[0]['items'])->toHaveCount(2)
        ->and($blocks[0]['items'][0])->toMatchArray([
            'description' => 'GARDENIA QUICKBITES CREAM ROLL',
            'quantity' => 1.0,
            'line_total' => 1.2,
        ])
        ->and($blocks[0]['items'][1])->toMatchArray([
            'description' => 'GARDENIA ORIG CLASSIC ENR.WHIT',
            'quantity' => 1.0,
            'line_total' => 3.0,
        ]);
});

test('parser accepts multiple invoice blocks in one message', function () {
    $text = <<<'TEXT'
myNEWS Bayu Residensi;
GARDENIA QUICKBITES CREAM ROLL, 1, 1.2;

7-Eleven Malaysia Sdn. Bhd.;
Hausboom Grapple 325, 1, 2;
TEXT;

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['merchant_name'])->toBe('myNEWS Bayu Residensi')
        ->and($blocks[1]['merchant_name'])->toBe('7-Eleven Malaysia Sdn. Bhd.')
        ->and($blocks[1]['items'][0]['line_total'])->toBe(2.0);
});

test('parser rejects non format text', function (string $text) {
    expect(ManualWhatsAppInvoiceParser::looksLike($text))->toBeFalse()
        ->and(ManualWhatsAppInvoiceParser::parse($text))->toBe([]);
})->with([
    'help' => 'help',
    'spend query' => 'How much did I spend this month?',
    'merchant only' => "Store Name;\n",
    'items without merchant' => "Item A, 1, 10;\n",
    'missing semicolons' => "Store Name\nItem A, 1, 10",
]);

test('parser preserves original casing', function () {
    $text = "My Store;\nItem One, 2, 20;";

    $blocks = ManualWhatsAppInvoiceParser::parse($text);

    expect($blocks[0]['merchant_name'])->toBe('My Store')
        ->and($blocks[0]['items'][0]['description'])->toBe('Item One');
});
