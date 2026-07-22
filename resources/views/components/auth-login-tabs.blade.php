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

    <x-filament::tabs
        class="tido-auth-login-tabs w-full"
        label="Sign-in method"
    >
        <x-filament::tabs.item
            :active="$otpActive"
            class="flex-1"
            wire:click="selectOtpLoginTab"
        >
            One-Time Password (OTP)
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="! $otpActive"
            class="flex-1"
            wire:click="selectPasswordLoginTab"
        >
            Email &amp; Password
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
