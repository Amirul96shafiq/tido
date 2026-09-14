<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('login page renders sign up cta at bottom without sign in via label', function () {
    $html = Livewire::test(Login::class)->html();

    expect($html)
        ->toContain('tido-auth-panel-switch')
        ->toContain('wire:target="selectSignUpTab"')
        ->toContain("Don't have an account?")
        ->toContain('Sign up')
        ->toContain('text-primary-600')
        ->toContain('wire:key="auth-panel-switch-sign-in"')
        ->toContain('wire:key="auth-cta-sign-up"')
        ->toContain('Sign in via')
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

test('sign up panel shows registration form and hides sign in form', function () {
    Livewire::test(Login::class)
        ->call('selectSignUpTab')
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

test('sign in panel keeps brand headline with tidy and done underlines', function () {
    Livewire::test(Login::class)
        ->assertSet('authPanel', 'sign-in')
        ->assertSeeHtml('Keep it <span class="underline">ti</span>dy. Get it <span class="underline">do</span>ne.')
        ->assertDontSee('tido-signup-greeting-heading', false);
});

test('switching back to sign in restores login method tabs and form', function () {
    Livewire::test(Login::class)
        ->call('selectSignUpTab')
        ->assertSet('authPanel', 'sign-up')
        ->assertSee('tido-signup-greeting-heading', false)
        ->call('selectSignInTab')
        ->assertSet('authPanel', 'sign-in')
        ->assertSee('tido-auth-login-tabs', false)
        ->assertSee('WhatsApp number')
        ->assertSee("Don't have an account?", false)
        ->assertSeeHtml('wire:key="tido-signin-heading"')
        ->assertSeeHtml('Keep it <span class="underline">ti</span>dy. Get it <span class="underline">do</span>ne.')
        ->assertDontSee('tido-signup-greeting-heading', false)
        ->assertDontSee('tidoSignupGreeting', false);
});

test('login mode tabs still switch between otp and password on sign in panel', function () {
    Livewire::test(Login::class)
        ->call('selectPasswordLoginTab')
        ->assertSet('loginMode', 'password')
        ->call('selectOtpLoginTab')
        ->assertSet('loginMode', 'phone');
});
