<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use Filament\Forms\Components\TextInput;
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

test('login page shows mode tabs without the info toast', function () {
    Livewire::test(Login::class)
        ->assertSee('Sign in via')
        ->assertSee('One-Time Password (OTP)')
        ->assertSee('Email & Password')
        ->assertDontSee('Sign in with email & password');

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertDontSee('Seamless login ready to use!')
        ->assertDontSee('Use your personal WhatsApp number to login via One-Time Password (OTP) code.')
        ->assertDontSee('tido-auth-login-toast', false)
        ->assertDontSee('tido-auth-login-toast-modal', false);
});

test('reset password page shows description below heading', function () {
    Livewire::test(ResetPassword::class)
        ->assertSee('Set a new password for the account.');
});

test('forgot password email field is not autofocused', function () {
    Livewire::test(RequestPasswordReset::class)
        ->assertSchemaComponentExists(
            'email',
            checkComponentUsing: function (TextInput $component): bool {
                expect($component->isAutofocused())->toBeFalse();

                return true;
            },
        );
});

test('reset password email field is not autofocused', function () {
    Livewire::test(ResetPassword::class)
        ->assertSchemaComponentExists(
            'email',
            checkComponentUsing: function (TextInput $component): bool {
                expect($component->isAutofocused())->toBeFalse();

                return true;
            },
        );
});

test('guest auth pages show auth menu with theme switcher and changelogs', function (string $url) {
    $this->get($url)
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->assertSee('images/favicon.png', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('Changelogs 🡥');
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
    fn () => route('filament.admin.auth.password-reset.expired'),
    fn () => route('filament.admin.auth.email-change-verification.expired'),
]);

test('auth menu chrome is flush top-right square with left and bottom borders', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $blade = (string) file_get_contents(resource_path('views/components/auth-menu.blade.php'));

    $expectedSize = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $block = Str::between($css, '.fi-auth-menu {', '.dark .fi-auth-menu {');

    expect($block)
        ->toContain('position: fixed;')
        ->toContain('top: 0;')
        ->toContain('right: 0;')
        ->toContain("width: {$expectedSize};")
        ->toContain("height: {$expectedSize};")
        ->toContain('border-bottom: 1px solid var(--color-gray-100);')
        ->toContain('border-left: 1px solid var(--color-gray-100);')
        ->and($blade)
        ->toContain('class="fi-auth-menu"')
        ->not->toContain('top-4')
        ->not->toContain('inset-e-4');
});
