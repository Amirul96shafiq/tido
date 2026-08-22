<?php

declare(strict_types=1);

use App\Support\WhatsAppTypingSession;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['services.evolution.whatsapp_typing_session_ttl_seconds' => 600]);
});

test('typing session activate isActive sender and deactivate lifecycle', function () {
    expect(WhatsAppTypingSession::isActive(42))->toBeFalse()
        ->and(WhatsAppTypingSession::sender(42))->toBeNull();

    WhatsAppTypingSession::activate(42, '60123456789');

    expect(WhatsAppTypingSession::isActive(42))->toBeTrue()
        ->and(WhatsAppTypingSession::sender(42))->toBe('60123456789')
        ->and(Cache::has(WhatsAppTypingSession::cacheKey(42)))->toBeTrue();

    WhatsAppTypingSession::deactivate(42);

    expect(WhatsAppTypingSession::isActive(42))->toBeFalse()
        ->and(WhatsAppTypingSession::sender(42))->toBeNull()
        ->and(Cache::has(WhatsAppTypingSession::cacheKey(42)))->toBeFalse();
});

test('typing session activate ignores invalid expense id or blank sender', function () {
    WhatsAppTypingSession::activate(0, '60123456789');
    WhatsAppTypingSession::activate(5, '   ');

    expect(WhatsAppTypingSession::isActive(0))->toBeFalse()
        ->and(WhatsAppTypingSession::isActive(5))->toBeFalse();
});

test('typing session activate is idempotent and refreshes sender', function () {
    WhatsAppTypingSession::activate(7, '60111111111');
    WhatsAppTypingSession::activate(7, '60222222222');

    expect(WhatsAppTypingSession::sender(7))->toBe('60222222222')
        ->and(WhatsAppTypingSession::isActive(7))->toBeTrue();
});
