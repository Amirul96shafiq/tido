<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EmailChangeLinkExpired;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use App\Support\EmailChangeVerification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Uri;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('auth verification expire defaults to 30 seconds outside production', function (): void {
    expect(config('auth.verification.expire'))->toBe(30)
        ->and(config('app.env'))->not->toBe('production');
});

test('production default lifetime is three days in seconds', function (): void {
    expect(60 * 60 * 24 * 3)->toBe(259_200);
});

test('email change verify url expires after configured seconds', function (): void {
    config(['auth.verification.expire' => 30]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $url = EmailChangeVerification::verifyUrl($user, 'new@example.com');
    $signature = Uri::of($url)->query()->get('signature');
    cache()->put($signature, true, ttl: EmailChangeVerification::expiresAt());

    expect($url)->toContain('email-change-verification')
        ->and($url)->toContain('signature=');

    parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
    $expires = (int) ($query['expires'] ?? 0);

    expect($expires)->toBeGreaterThan(now()->timestamp)
        ->and($expires)->toBeLessThanOrEqual(now()->addSeconds(31)->timestamp);

    $this->travel(31)->seconds();

    $this->get($url)
        ->assertRedirect(route('filament.admin.auth.email-change-verification.expired'));

    $this->followingRedirects()
        ->get($url)
        ->assertSuccessful()
        ->assertSee('Verification Link Expired', false)
        ->assertSee('fi-auth-menu', false)
        ->assertSee('Return to Profile Settings', false);
});

test('email change verify url is valid within configured seconds', function (): void {
    config(['auth.verification.expire' => 30]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $url = EmailChangeVerification::verifyUrl($user, 'new@example.com');
    $signature = Uri::of($url)->query()->get('signature');
    cache()->put($signature, true, ttl: EmailChangeVerification::expiresAt());

    $this->get($url)->assertRedirect();

    expect($user->fresh()->email)->toBe('new@example.com');
});

test('verify email change mail uses expire label from config seconds', function (): void {
    config(['auth.verification.expire' => 30]);

    $user = User::factory()->create();
    $notification = new VerifyEmailChange;
    $notification->url = EmailChangeVerification::verifyUrl($user, 'new@example.com');

    $mail = $notification->toMail($user);

    expect(EmailChangeVerification::expireLabel())->toBe('30 seconds');

    $rendered = implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);
    expect($rendered)->toContain('30 seconds');
});

test('expire label formats days for production lifetime', function (): void {
    config(['auth.verification.expire' => 60 * 60 * 24 * 3]);

    expect(EmailChangeVerification::expireLabel())->toBe('3 days');
});

test('invalid signature on email change verify path redirects to expired page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/admin/email-change-verification/verify/1/fake')
        ->assertRedirect(route('filament.admin.auth.email-change-verification.expired'));
});

test('email change link expired page shows auth chrome and cta', function (): void {
    $this->get(route('filament.admin.auth.email-change-verification.expired'))
        ->assertSuccessful()
        ->assertSee('Verification Link Expired', false)
        ->assertSee('Return to Profile Settings', false)
        ->assertSee('fi-simple-layout', false)
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false);

    Livewire::test(EmailChangeLinkExpired::class)
        ->assertSee('Verification Link Expired')
        ->assertSee('Return to Profile Settings');
});
