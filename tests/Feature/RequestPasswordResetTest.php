<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest can open forgot password page', function () {
    $this->get('/admin/password-reset/request')->assertSuccessful();
});

test('forgot password page shows description below heading', function () {
    $page = Livewire::test(RequestPasswordReset::class);

    expect($page->instance()->getSubheading())
        ->toBe('Enter the registered email address to receive a password reset link.');
});

test('forgot password page shows back to login link below the form', function () {
    Livewire::test(RequestPasswordReset::class)
        ->assertSee('back to login');
});

test('login page shows email password sign in link', function () {
    Livewire::test(Login::class)
        ->assertSee('Sign in with email & password');
});

test('reset password page shows description below heading', function () {
    Livewire::test(ResetPassword::class)
        ->assertSee('Set a new password for the account.');
});

test('guest auth pages show auth menu with theme switcher and changelogs', function (string $url) {
    $this->get($url)
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->assertSee('images/favicon.png', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('Changelogs');
})->with([
    fn () => '/admin/login',
    fn () => '/admin/password-reset/request',
    fn () => URL::temporarySignedRoute(
        'filament.admin.auth.password-reset.reset',
        now()->addHour(),
        [
            'email' => 'admin@tido.local',
            'token' => 'test-token',
        ],
    ),
]);
