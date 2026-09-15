<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('login page renders sign up cta linking to register without sign in via label', function () {
    $html = Livewire::test(Login::class)->html();

    expect($html)
        ->toContain('tido-auth-panel-switch')
        ->toContain('/admin/register')
        ->toContain('wire:navigate')
        ->toContain("Don't have an account?")
        ->toContain('Sign up')
        ->toContain('text-primary-600')
        ->toContain('wire:key="auth-panel-switch-sign-in"')
        ->toContain('wire:key="auth-cta-sign-up"')
        ->toContain('Sign in via')
        ->not->toContain('wire:target="selectSignUpTab"')
        ->not->toContain('tido-auth-intent-tabs')
        ->not->toContain('data-tippy-always');
});

test('sign in panel shows login method tabs and form by default', function () {
    Livewire::test(Login::class)
        ->assertSet('authPanel', 'sign-in')
        ->assertSet('loginMode', 'phone')
        ->assertSee('tido-auth-login-tabs', false)
        ->assertSee('Sign in via')
        ->assertSee('One-Time Password (OTP)')
        ->assertSee('Email & Password')
        ->assertSee('WhatsApp number')
        ->assertDontSee('tido-auth-sign-up-coming-soon', false);
});

test('register page shows registration form and hides sign in form', function () {
    Livewire::test(Register::class)
        ->assertSet('authPanel', 'sign-up')
        ->assertSet('signupMode', 'form')
        ->assertSee('Email address')
        ->assertSee('Confirm password')
        ->assertSee('Start Sign Up')
        ->assertSee('Hello!')
        ->assertSee('tido-signup-greeting-heading', false)
        ->assertSee('tido-signup-greeting-heading__caret', false)
        ->assertSee('tidoSignupGreeting', false)
        ->assertDontSee('tido-auth-google-sign-in-btn--disabled', false)
        ->assertSee('Already have an Account?')
        ->assertSee('Sign in')
        ->assertSee('wire:key="auth-cta-sign-in"', false)
        ->assertDontSee('tido-auth-login-tabs', false)
        ->assertDontSee('WhatsApp number')
        ->assertDontSee('tido-auth-sign-up-coming-soon', false)
        ->assertDontSee("Don't have an account?");
});

test('sign in panel shows welcome back typewriter heading for localized visitors', function () {
    Livewire::test(Login::class)
        ->assertSet('authPanel', 'sign-in')
        ->assertSee('Welcome Back!')
        ->assertSee('Selamat kembali!', false)
        ->assertSee('wire:key="tido-signin-welcome"', false)
        ->assertSee('tido-signup-greeting-heading', false)
        ->assertSee('tidoSignupGreeting', false);
});

test('sign in panel shows static welcome back for english only visitors', function (): void {
    $this->withHeaders(['CF-IPCountry' => 'US'])
        ->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Welcome Back!')
        ->assertDontSee('wire:key="tido-signin-welcome"', false)
        ->assertDontSee('tidoSignupGreeting', false);
});

test('sign in otp step hides welcome typewriter heading', function (): void {
    Livewire::test(Login::class)
        ->set('loginMode', 'otp')
        ->assertSee('Enter the code')
        ->assertDontSee('wire:key="tido-signin-welcome"', false)
        ->assertDontSee('tidoSignupGreeting', false);
});

test('select sign up tab redirects to register page', function () {
    Livewire::test(Login::class)
        ->call('selectSignUpTab')
        ->assertRedirect(route('filament.admin.auth.register'));
});

test('select sign in tab on register redirects to login page', function () {
    Livewire::test(Register::class)
        ->call('selectSignInTab')
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('login mode tabs still switch between otp and password on sign in panel', function () {
    Livewire::test(Login::class)
        ->call('selectPasswordLoginTab')
        ->assertSet('loginMode', 'password')
        ->call('selectOtpLoginTab')
        ->assertSet('loginMode', 'phone');
});

test('guest can open register page', function (): void {
    $this->get('/admin/register')
        ->assertSuccessful()
        ->assertSee('Email address')
        ->assertSee('Start Sign Up')
        ->assertSee('tido-signup-greeting-heading', false);
});
