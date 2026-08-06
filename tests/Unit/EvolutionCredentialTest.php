<?php

declare(strict_types=1);

use App\Support\EvolutionCredential;
use Tests\TestCase;

uses(TestCase::class);

test('evolution credentials require a minimum length and reject placeholders', function (): void {
    expect(EvolutionCredential::isValid('change-me'))->toBeFalse()
        ->and(EvolutionCredential::isValid(str_repeat('x', EvolutionCredential::MINIMUM_LENGTH - 1)))->toBeFalse()
        ->and(EvolutionCredential::isValid(str_repeat('x', EvolutionCredential::MINIMUM_LENGTH)))->toBeTrue();
});

test('evolution credentials must be distinct', function (): void {
    $apiKey = str_repeat('a', EvolutionCredential::MINIMUM_LENGTH);
    $webhookSecret = str_repeat('b', EvolutionCredential::MINIMUM_LENGTH);

    expect(EvolutionCredential::areDistinct($apiKey, $webhookSecret))->toBeTrue()
        ->and(EvolutionCredential::areDistinct($apiKey, $apiKey))->toBeFalse()
        ->and(EvolutionCredential::areDistinct('change-me', $webhookSecret))->toBeFalse();
});
