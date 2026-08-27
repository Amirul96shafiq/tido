@props([
    'redirectUrl' => '',
])

<div class="tido-auth-google-sign-in-wrap">
    <div class="tido-auth-google-divider" aria-hidden="true">
        <span>or</span>
    </div>

    <a
        href="{{ $redirectUrl }}"
        class="tido-auth-google-sign-in-btn fi-btn fi-size-md fi-color-gray"
    >
        <x-filament::icon icon="icon-google" class="icon-google h-5 w-5 shrink-0" />
        <span>Continue with Google</span>
    </a>
</div>
