<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EmailSignupOtpNotification;
use App\Services\EmailSignupOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();

    config([
        'services.email_signup.dev_otp' => '654321',
        'services.email_signup.dev_addresses' => 'dev-signup@example.com',
    ]);
});

test('send queues otp notification for new emails', function (): void {
    $result = app(EmailSignupOtpService::class)->send('new-user@example.com', 'password-password');

    expect($result['email'])->toBe('new-user@example.com')
        ->and($result['name'])->toBe('New User');

    Notification::assertSentOnDemand(EmailSignupOtpNotification::class);
});

test('send does not mail dev addresses when dev otp is configured', function (): void {
    app(EmailSignupOtpService::class)->send('dev-signup@example.com', 'password-password');

    Notification::assertNothingSent();
});

test('send rejects duplicate email addresses', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    app(EmailSignupOtpService::class)->send('taken@example.com', 'password-password');
})->throws(RuntimeException::class, 'Unable to send a confirmation code for this email.');

test('verify returns pending signup data for valid otp', function (): void {
    $service = app(EmailSignupOtpService::class);
    $service->send('dev-signup@example.com', 'password-password');

    $pending = $service->verify('dev-signup@example.com', '654321');

    expect($pending)->toBeArray()
        ->and($pending['email'])->toBe('dev-signup@example.com')
        ->and($pending['password'])->toBe('password-password')
        ->and($pending['name'])->toBe('Dev Signup');
});

test('verify returns null for invalid otp codes', function (): void {
    $service = app(EmailSignupOtpService::class);
    $service->send('dev-signup@example.com', 'password-password');

    expect($service->verify('dev-signup@example.com', '000000'))->toBeNull();
});

test('send enforces resend cooldown', function (): void {
    $service = app(EmailSignupOtpService::class);
    $service->send('dev-signup@example.com', 'password-password');

    $service->send('dev-signup@example.com', 'password-password');
})->throws(RuntimeException::class, 'Please wait');

test('deriveNameFromEmail formats local parts', function (): void {
    $service = app(EmailSignupOtpService::class);

    expect($service->deriveNameFromEmail('jane.doe_smith@example.com'))->toBe('Jane Doe Smith');
});
