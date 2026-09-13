@props([
    'authPanel' => 'sign-in',
])

<div
    {{ $attributes->class(['tido-auth-panel-switch']) }}
    wire:key="auth-panel-switch-{{ $authPanel }}"
>
    @if ($authPanel === 'sign-in')
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            <span>Don't have an account?</span>
            <button
                type="button"
                wire:key="auth-cta-sign-up"
                wire:click="selectSignUpTab"
                class="tido-auth-panel-switch__cta ms-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                <span wire:loading.remove wire:target="selectSignUpTab">Sign up</span>
                <x-filament::loading-indicator
                    class="inline-block h-4 w-4 align-[-0.125em]"
                    wire:loading
                    wire:target="selectSignUpTab"
                />
            </button>
        </p>
    @else
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            <span>Already have an Account?</span>
            <button
                type="button"
                wire:key="auth-cta-sign-in"
                wire:click="selectSignInTab"
                class="tido-auth-panel-switch__cta ms-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                <span wire:loading.remove wire:target="selectSignInTab">Sign in</span>
                <x-filament::loading-indicator
                    class="inline-block h-4 w-4 align-[-0.125em]"
                    wire:loading
                    wire:target="selectSignInTab"
                />
            </button>
        </p>
    @endif
</div>
