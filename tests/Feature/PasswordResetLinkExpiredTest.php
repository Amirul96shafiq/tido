<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\PasswordResetLinkExpired;
use App\Support\AuthLinkExpiry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('guest can open password reset link expired page', function (): void {
    $this->get(route('filament.admin.auth.password-reset.expired'))
        ->assertSuccessful()
        ->assertSee('Link Expired', false)
        ->assertSee('Return to Login', false)
        ->assertSee('fi-simple-layout', false)
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertDontSee('icon-container', false);
});

test('password reset link expired page shows heading and cta', function (): void {
    Livewire::test(PasswordResetLinkExpired::class)
        ->assertSee('Link Expired')
        ->assertSee('Return to Login')
        ->assertSee('password reset link has expired');
});

test('expired password reset signature redirects to link expired page', function (): void {
    config(['auth.verification.expire' => 30]);

    $url = URL::temporarySignedRoute(
        'filament.admin.auth.password-reset.reset',
        AuthLinkExpiry::expiresAt(),
        [
            'email' => 'admin@tido.local',
            'token' => 'test-token',
        ],
    );

    $this->travel(31)->seconds();

    $this->get($url)
        ->assertRedirect(route('filament.admin.auth.password-reset.expired'));

    $this->get(route('filament.admin.auth.password-reset.expired'))
        ->assertSuccessful()
        ->assertSee('Link Expired', false)
        ->assertSee('fi-auth-menu', false);
});

test('invalid password reset signature redirects to link expired page', function (): void {
    $this->get('/admin/password-reset/reset?email=admin@tido.local&token=fake&signature=invalid')
        ->assertRedirect(route('filament.admin.auth.password-reset.expired'));
});

test('guest auth pages show auth menu including password reset expired', function (string $url): void {
    $this->get($url)
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('Changelogs 🡥');
})->with([
    fn () => route('filament.admin.auth.password-reset.expired'),
    fn () => route('filament.admin.auth.email-change-verification.expired'),
]);
