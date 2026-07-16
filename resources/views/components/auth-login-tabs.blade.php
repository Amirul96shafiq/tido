@props([
    'loginMode' => 'phone',
])

@php
    $otpActive = in_array($loginMode, ['phone', 'otp'], true);
    // Blade does not compile @js() inside <x-*> attributes; emit plain Alpine only.
    $otpTabClick = $otpActive ? '' : 'softSwitch()';
    $passwordTabClick = $otpActive ? 'softSwitch()' : '';
@endphp

<div
    {{ $attributes->class(['tido-auth-login-tabs-wrap']) }}
    x-data="{
        pendingSwitch: false,
        softSwitch() {
            const page = this.$el.closest('.fi-simple-page')
            if (! page) {
                return
            }

            this.pendingSwitch = true
            page.classList.remove('tido-login-mode-enter')
            page.classList.add('tido-login-mode-leave')
        },
        finishSwitch() {
            const page = this.$el.closest('.fi-simple-page')
            if (! page || ! this.pendingSwitch) {
                return
            }

            this.pendingSwitch = false
            page.classList.remove('tido-login-mode-leave')
            page.classList.remove('tido-login-mode-enter')
            void page.offsetWidth
            page.classList.add('tido-login-mode-enter')
        },
    }"
    x-init="
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                queueMicrotask(() => finishSwitch())
            })
        })
    "
>
    <x-filament::tabs
        class="tido-auth-login-tabs w-full"
        label="Sign-in method"
    >
        <x-filament::tabs.item
            :active="$otpActive"
            class="flex-1"
            wire:click="selectOtpLoginTab"
            x-on:click="{{ $otpTabClick }}"
        >
            One-Time Password (OTP)
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="! $otpActive"
            class="flex-1"
            wire:click="selectPasswordLoginTab"
            x-on:click="{{ $passwordTabClick }}"
        >
            Email &amp; Password
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
