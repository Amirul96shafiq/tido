<?php

declare(strict_types=1);

use App\Support\WhatsAppSpendingCommandParser;

test('parse returns null when spend or total is absent', function () {
    expect(WhatsAppSpendingCommandParser::parse('help'))->toBeNull();
});

test('parse defaults to summary for current month', function () {
    $result = WhatsAppSpendingCommandParser::parse('How much did I spend this month?');

    expect($result)->toMatchArray([
        'mode' => WhatsAppSpendingCommandParser::MODE_SUMMARY,
        'month' => now()->format('Y-m'),
    ]);
});

test('parse recognizes total keyword', function () {
    $result = WhatsAppSpendingCommandParser::parse('total');

    expect($result)->not->toBeNull()
        ->and($result['mode'])->toBe(WhatsAppSpendingCommandParser::MODE_SUMMARY);
});

test('parse recognizes spend sub-command modes', function (string $text, string $mode) {
    $result = WhatsAppSpendingCommandParser::parse($text);

    expect($result)->not->toBeNull()
        ->and($result['mode'])->toBe($mode);
})->with([
    ['spend labels', WhatsAppSpendingCommandParser::MODE_LABELS],
    ['spend merchants', WhatsAppSpendingCommandParser::MODE_MERCHANTS],
    ['spend budgets', WhatsAppSpendingCommandParser::MODE_BUDGETS],
    ['spend trend', WhatsAppSpendingCommandParser::MODE_TREND],
    ['spend payment', WhatsAppSpendingCommandParser::MODE_PAYMENT],
    ['spend recent', WhatsAppSpendingCommandParser::MODE_RECENT],
    ['spend last', WhatsAppSpendingCommandParser::MODE_RECENT],
]);

test('parse resolves last month period', function () {
    $result = WhatsAppSpendingCommandParser::parse('spend last month');

    expect($result)->toMatchArray([
        'mode' => WhatsAppSpendingCommandParser::MODE_SUMMARY,
        'month' => now()->copy()->subMonth()->format('Y-m'),
    ]);
});

test('parse resolves explicit year-month period', function () {
    $result = WhatsAppSpendingCommandParser::parse('spend 2025-03');

    expect($result)->toMatchArray([
        'mode' => WhatsAppSpendingCommandParser::MODE_SUMMARY,
        'month' => '2025-03',
    ]);
});

test('parse resolves named month with year', function () {
    $result = WhatsAppSpendingCommandParser::parse('spend labels march 2024');

    expect($result)->toMatchArray([
        'mode' => WhatsAppSpendingCommandParser::MODE_LABELS,
        'month' => '2024-03',
    ]);
});
