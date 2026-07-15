<?php

declare(strict_types=1);

use App\Services\ReceiptParseNormalizer;
use Carbon\Carbon;

test('toMoney rejects none null and non numeric strings', function () {
    $normalizer = new ReceiptParseNormalizer;

    expect($normalizer->toMoney('None'))->toBe(0.0)
        ->and($normalizer->toMoney('null'))->toBe(0.0)
        ->and($normalizer->toMoney(''))->toBe(0.0)
        ->and($normalizer->toMoney(null))->toBe(0.0)
        ->and($normalizer->toMoney('abc'))->toBe(0.0)
        ->and($normalizer->toMoney('5.40'))->toBe(5.4)
        ->and($normalizer->toMoney('RM 5.40'))->toBe(5.4)
        ->and($normalizer->toMoney(['value' => 2.2, 'currency' => 'MYR']))->toBe(2.2);
});

test('parseDateTime handles malaysia dd/mm/yy and named months', function () {
    $normalizer = new ReceiptParseNormalizer;

    expect($normalizer->parseDateTime('14/07/26 20:56:20')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-14 20:56:20');

    expect($normalizer->parseDateTime('14-Jul-2026 21:07:20')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-14 21:07:20');

    expect($normalizer->parseDateTime('14/07/2026 21:07:20')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-14 21:07:20');
});

test('parseDateTime handles audit failure strings without month day swap', function () {
    $normalizer = new ReceiptParseNormalizer;

    expect($normalizer->parseDateTime('06/07/26T18:36:11.000Z')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-06 18:36:11');

    expect($normalizer->parseDateTime('11/07/26T17:20')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-11 17:20:00');

    expect($normalizer->parseDateTime('14/07/26T')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-14 00:00:00');

    expect($normalizer->parseDateTime('08/7/26 12:33:20')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-08 12:33:20');

    expect($normalizer->parseDateTime('12-Jul-2026 18:06:50')?->format('Y-m-d H:i:s'))
        ->toBe('2026-07-12 18:06:50');
});

test('parseDateTime returns null for empty or unparseable values instead of now', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    $normalizer = new ReceiptParseNormalizer;

    expect($normalizer->parseDateTime(null))->toBeNull()
        ->and($normalizer->parseDateTime(''))->toBeNull()
        ->and($normalizer->parseDateTime('not-a-date'))->toBeNull();

    Carbon::setTestNow();
});

test('parseDateTime parses year first iso but sanity rejects hallucinated years', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'Asia/Kuala_Lumpur'));

    $normalizer = new ReceiptParseNormalizer;

    $parsed = $normalizer->parseDateTime('2018-07-13T16:32:22Z');

    expect($parsed?->format('Y-m-d H:i:s'))->toBe('2018-07-13 16:32:22')
        ->and($normalizer->isDateTimeSane($parsed))->toBeFalse();

    $sane = $normalizer->parseDateTime('2026-07-14 21:07:20');

    expect($normalizer->isDateTimeSane($sane))->toBeTrue()
        ->and($normalizer->isDateTimeSane(null))->toBeFalse();

    Carbon::setTestNow();
});

test('isDateTimeSane rejects dates after tomorrow', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'Asia/Kuala_Lumpur'));

    $normalizer = new ReceiptParseNormalizer;
    $future = Carbon::parse('2026-11-07 17:20:00');

    expect($normalizer->isDateTimeSane($future))->toBeFalse();

    Carbon::setTestNow();
});

test('normalize strips company registration style invoice numbers and none money', function () {
    $normalizer = new ReceiptParseNormalizer;

    $normalized = $normalizer->normalize([
        'merchant_name' => '  mynews retail sdn bhd  ',
        'invoice_number' => '199401020616 (306295-X CBP 000709361664',
        'date_time' => '14-Jul-2026 21:07:20',
        'subtotal' => '5.40',
        'total_tax' => 'None',
        'discount_total' => 'None',
        'rounding_amount' => 'None',
        'total_amount' => '5.40',
        'currency' => 'MYR',
        'payment_method' => 'mastercard',
        'items' => [
            [
                'description' => 'FN SEASONS ICE LEMON TEA 300ML',
                'quantity' => '1',
                'unit_price' => '2.20',
                'line_total' => '2.20',
                'serial_number' => '9556072080026',
                'label' => 'Food & Dining',
            ],
            [
                'description' => 'OBALAB TRIANGLE CAKE',
                'quantity' => '1',
                'unit_price' => '3.20',
                'line_total' => '3.20',
                'barcode' => 'none',
                'label' => 'Groceries & Household',
            ],
            [
                'description' => '',
                'quantity' => '1',
                'unit_price' => '0',
                'line_total' => '0',
                'label' => '',
            ],
        ],
    ]);

    expect($normalized['merchant_name'])->toBe('mynews retail sdn bhd')
        ->and($normalized['invoice_number'])->toBeNull()
        ->and($normalized['total_tax'])->toBe(0.0)
        ->and($normalized['items'])->toHaveCount(2)
        ->and($normalized['items'][0]['serial_number'])->toBe('9556072080026')
        ->and($normalized['items'][1]['serial_number'])->toBeNull()
        ->and($normalized['items'][0]['label'])->toBe('Food & Dining')
        ->and($normalized['items'][1]['label'])->toBe('Groceries & Household')
        ->and($normalizer->amountsReconcile($normalized))->toBeTrue();
});

test('amountsReconcile fails when line items disagree with total', function () {
    $normalizer = new ReceiptParseNormalizer;

    $normalized = $normalizer->normalize([
        'merchant_name' => 'TMG',
        'date_time' => '14/07/26 20:56:20',
        'subtotal' => 3.6,
        'total_tax' => 0,
        'discount_total' => 0,
        'rounding_amount' => 0,
        'total_amount' => 4.9,
        'items' => [
            [
                'description' => 'CHACHO',
                'quantity' => 1,
                'unit_price' => 3.6,
                'line_total' => 3.6,
                'label' => 'Groceries & Household',
            ],
            [
                'description' => 'BAD',
                'quantity' => 5,
                'unit_price' => 2.1,
                'line_total' => 11.0,
                'label' => 'Groceries & Household',
            ],
        ],
    ]);

    expect($normalizer->amountsReconcile($normalized))->toBeFalse();
});

test('normalize accepts legacy suggested_category key for label', function () {
    $normalizer = new ReceiptParseNormalizer;

    $normalized = $normalizer->normalize([
        'merchant_name' => 'Store',
        'date_time' => '2026-07-15 10:00:00',
        'subtotal' => 10.00,
        'total_tax' => 0,
        'discount_total' => 0,
        'rounding_amount' => 0,
        'total_amount' => 10.00,
        'items' => [
            [
                'description' => 'Meal',
                'quantity' => 1,
                'unit_price' => 10.00,
                'line_total' => 10.00,
                'suggested_category' => 'Food & Dining',
            ],
        ],
    ]);

    expect($normalized['items'][0]['label'])->toBe('Food & Dining');
});
