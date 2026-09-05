@props([
    'loginMode' => 'phone',
])

@php
    $otpActive = in_array($loginMode, ['phone', 'otp'], true);
@endphp

<div {{ $attributes->class(['tido-auth-login-tabs-wrap']) }}>
    <p class="tido-auth-login-tabs-label mb-2 text-left text-sm font-medium text-gray-950 dark:text-white">
        Sign in via
    </p>

    <x-filament::tabs class="tido-auth-login-tabs w-full" label="Sign-in method">
        <x-filament::tabs.item
            :active="$otpActive"
            class="flex-1"
            wire:click="selectOtpLoginTab"
            aria-label="One-Time Password (OTP)"
        >
            <span wire:loading.remove wire:target="selectOtpLoginTab">
                One-Time Password (OTP)
            </span>
            <x-filament::loading-indicator
                class="h-5 w-5"
                wire:loading
                wire:target="selectOtpLoginTab"
            />
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="! $otpActive"
            class="flex-1"
            wire:click="selectPasswordLoginTab"
            aria-label="Email & Password"
        >
            <span wire:loading.remove wire:target="selectPasswordLoginTab">
                Email &amp; Password
            </span>
            <x-filament::loading-indicator
                class="h-5 w-5"
                wire:loading
                wire:target="selectPasswordLoginTab"
            />
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
