<?php

declare(strict_types=1);

use App\Support\SignInWelcomeCopy;

test('localized welcome for malaysia is selamat kembali', function (): void {
    expect(SignInWelcomeCopy::localizedForCountry('MY'))
        ->toBe('Selamat kembali!');
});

test('localized welcome for unknown country falls back to english', function (): void {
    expect(SignInWelcomeCopy::localizedForCountry('US'))
        ->toBe('Welcome Back!')
        ->and(SignInWelcomeCopy::localizedForCountry(null))
        ->toBe('Welcome Back!');
});

test('typewriter phrases alternate welcome back and localized greeting', function (): void {
    expect(SignInWelcomeCopy::typewriterPhrases('MY'))
        ->toBe(['Welcome Back!', 'Selamat kembali!']);
});

test('typewriter phrases are null when localized greeting matches english', function (): void {
    expect(SignInWelcomeCopy::typewriterPhrases('US'))
        ->toBeNull();
});
