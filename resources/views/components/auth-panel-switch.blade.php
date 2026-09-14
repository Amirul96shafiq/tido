@props([
    'authPanel' => 'sign-in',
])

<div
    {{ $attributes->class(['tido-auth-panel-switch']) }}
    wire:key="auth-panel-switch-{{ $authPanel }}"
>
    @if ($authPanel === 'sign-in')
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span>Don't have an account?</span>
            <a
                href="{{ filament()->getRegistrationUrl() }}"
                wire:navigate
                wire:key="auth-cta-sign-up"
                class="tido-auth-panel-switch__cta ms-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                Sign up
            </a>
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span>Already have an Account?</span>
            <a
                href="{{ filament()->getLoginUrl() }}"
                wire:navigate
                wire:key="auth-cta-sign-in"
                class="tido-auth-panel-switch__cta ms-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                Sign in
            </a>
        </p>
    @endif
</div>
