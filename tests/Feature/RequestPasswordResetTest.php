<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
