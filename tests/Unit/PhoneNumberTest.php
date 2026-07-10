<?php

declare(strict_types=1);

use App\Support\PhoneNumber;
use Tests\TestCase;

uses(TestCase::class);

test('normalizes local Malaysian numbers to 60 prefix', function () {
    expect(PhoneNumber::normalize('0123456789'))->toBe('60123456789');
});

test('normalizes plus-prefixed E.164 numbers', function () {
    expect(PhoneNumber::normalize('+60123456789'))->toBe('60123456789');
});

test('keeps already-normalized digits', function () {
    expect(PhoneNumber::normalize('60123456789'))->toBe('60123456789');
});

test('rejects empty and invalid values', function (mixed $value) {
    expect(PhoneNumber::normalize(is_string($value) ? $value : null))->toBeNull()
        ->and(PhoneNumber::isValid(is_string($value) ? $value : null))->toBeFalse();
})->with([
    null,
    '',
    '   ',
    '12345',
    'abc',
    '+1-555-0100',
]);

test('parseList splits and normalizes multiple numbers', function () {
    expect(PhoneNumber::parseList('60111111111, 0122222222;+60133333333'))
        ->toBe(['60111111111', '60122222222', '60133333333']);
});

test('parseList ignores invalid entries', function () {
    expect(PhoneNumber::parseList('60111111111, abc, 123'))
        ->toBe(['60111111111']);
});

test('allowedWhatsAppSenders includes primary and extras', function () {
    config([
        'services.evolution.personal_number' => '60123456789',
        'services.evolution.personal_extra_numbers' => '60111111111,60122222222',
    ]);

    expect(PhoneNumber::allowedWhatsAppSenders())
        ->toBe(['60123456789', '60111111111', '60122222222']);
});

test('isAllowedWhatsAppSender matches primary and extras only', function () {
    config([
        'services.evolution.personal_number' => '60123456789',
        'services.evolution.personal_extra_numbers' => '60111111111',
    ]);

    expect(PhoneNumber::isAllowedWhatsAppSender('60123456789'))->toBeTrue()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60111111111'))->toBeTrue()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60199999999'))->toBeFalse();
});
