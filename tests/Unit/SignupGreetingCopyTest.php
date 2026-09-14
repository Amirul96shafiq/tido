<?php

declare(strict_types=1);

use App\Support\SignupGreetingCopy;

test('localized greeting for malaysia is hai', function (): void {
    expect(SignupGreetingCopy::localizedForCountry('MY'))
        ->toBe('Hai!');
});

test('localized greeting for unknown country falls back to hello', function (): void {
    expect(SignupGreetingCopy::localizedForCountry('US'))
        ->toBe('Hello!')
        ->and(SignupGreetingCopy::localizedForCountry(null))
        ->toBe('Hello!');
});

test('typewriter phrases alternate hello and localized greeting', function (): void {
    expect(SignupGreetingCopy::typewriterPhrases('MY'))
        ->toBe(['Hello!', 'Hai!']);
});

test('typewriter phrases are null when localized greeting matches english', function (): void {
    expect(SignupGreetingCopy::typewriterPhrases('US'))
        ->toBeNull();
});
