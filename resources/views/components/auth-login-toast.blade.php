<div
    class="tido-auth-login-toast"
    x-data="{ open: true }"
    x-show="open"
    x-cloak
>
    <span class="tido-auth-login-toast-icon" aria-hidden="true">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
        <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
    </span>

    <div class="tido-auth-login-toast-body">
        <p class="tido-auth-login-toast-title">Seamless login ready to use!</p>
        <p class="tido-auth-login-toast-description">
            Use your personal WhatsApp number to login via One-Time Password (OTP) code.
        </p>
    </div>

    <button
        type="button"
        class="tido-auth-login-toast-close"
        aria-label="Dismiss"
        x-tooltip="{
            content: @js('Dismiss'),
            theme: $store.theme,
        }"
        x-on:click="open = false"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
        </svg>
    </button>
</div>
