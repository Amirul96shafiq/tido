<?php

declare(strict_types=1);

use App\Support\FieldCharacterLimits;

test('truncate leaves short values unchanged', function () {
    expect(FieldCharacterLimits::truncate('Ada', 20))->toBe('Ada')
        ->and(FieldCharacterLimits::truncate('', 20))->toBe('')
        ->and(FieldCharacterLimits::truncate(null, 20))->toBe('');
});

test('truncate cuts to the max character count', function () {
    expect(FieldCharacterLimits::truncate(str_repeat('a', 30), 25))->toBe(str_repeat('a', 25))
        ->and(FieldCharacterLimits::truncate('café extra', 4))->toBe('café');
});

test('counter html binds the danger class at the limit', function () {
    $html = FieldCharacterLimits::counterHtml(80)->toHtml();

    expect($html)
        ->toContain('x-bind:class')
        ->toContain(FieldCharacterLimits::AT_LIMIT_CLASS)
        ->toContain('\u003E= 80');
});

test('notes counter binds the danger class at the plaintext limit', function () {
    $attributes = FieldCharacterLimits::counterText(FieldCharacterLimits::NOTES, rich: true)->getExtraAttributes();

    expect($attributes['class'])->toContain(FieldCharacterLimits::EXTRA_CLASS)
        ->and($attributes['x-bind:class'])
        ->toContain(FieldCharacterLimits::AT_LIMIT_CLASS)
        ->toContain('\u003E= '.FieldCharacterLimits::NOTES);
});
