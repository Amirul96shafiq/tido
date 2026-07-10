<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\WhatsAppLoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '60123456789',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);
});

test('send stores hashed otp and posts to evolution', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    expect(app(WhatsAppLoginOtpService::class)->send($user))->toBeTrue();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/tido')
            && str_contains((string) $request['number'], '60123456789')
            && str_contains((string) $request['text'], 'tido login code');
    });

    expect(Cache::has('wa_login_otp:'.$user->id))->toBeTrue();
});

test('verify accepts the sent code and rejects wrong codes', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();
    $service = app(WhatsAppLoginOtpService::class);

    $service->send($user);

    $payload = Cache::get('wa_login_otp:'.$user->id);
    expect($payload)->toBeArray();

    // Recover plaintext by brute-forcing the known 6-digit space is too heavy;
    // instead re-hash check: plant a known code.
    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '123456'),
        'attempts' => 0,
    ], 600);

    expect($service->verify($user, '000000'))->toBeFalse()
        ->and($service->verify($user, '123456'))->toBeTrue()
        ->and(Cache::has('wa_login_otp:'.$user->id))->toBeFalse();
});

test('send refuses when user has no phone', function () {
    $user = User::factory()->create(['phone' => null]);

    expect(fn () => app(WhatsAppLoginOtpService::class)->send($user))
        ->toThrow(RuntimeException::class, 'User does not have a valid WhatsApp phone number.');
});

test('send rate-limits resends within cooldown', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();
    $service = app(WhatsAppLoginOtpService::class);

    $service->send($user);

    expect($service->cooldownRemainingSeconds($user))->toBeGreaterThan(0)
        ->and($service->cooldownEndsAt($user))->toBeInt()
        ->and(fn () => $service->send($user))
        ->toThrow(RuntimeException::class, 'Please wait');
});

test('cooldown remaining drops to zero after cache expiry', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();
    $service = app(WhatsAppLoginOtpService::class);

    $service->send($user);
    expect($service->cooldownRemainingSeconds($user))->toBeGreaterThan(0);

    Cache::flush();

    expect($service->cooldownRemainingSeconds($user))->toBe(0)
        ->and($service->cooldownEndsAt($user))->toBeNull();
});

test('send fails safely when evolution returns an error', function () {
    Http::swap(new Factory);
    Http::fake([
        '*' => Http::response(['error' => 'down'], 500),
    ]);

    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    expect(fn () => app(WhatsAppLoginOtpService::class)->send($user))
        ->toThrow(RuntimeException::class, 'Failed to send WhatsApp code');
});
