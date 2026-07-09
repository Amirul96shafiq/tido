<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

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
