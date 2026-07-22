@php
    $toastTitle = 'Seamless login ready to use!';
    $toastDescription = 'Use your personal WhatsApp number to login via One-Time Password (OTP) code.';
@endphp

<div
    class="tido-auth-login-toast-root"
    x-data="{
        dismissed: false,
        isMobile: window.matchMedia('(max-width: 1023px)').matches,
        loginMode: $wire.entangle('loginMode'),
        get showInline() {
            return ! this.isMobile && ! this.dismissed
        },
        get showModal() {
            return this.isMobile
                && ! this.dismissed
                && (this.loginMode === 'phone' || this.loginMode === 'password')
        },
        syncViewport() {
            this.isMobile = window.matchMedia('(max-width: 1023px)').matches
        },
        dismiss() {
            this.dismissed = true
        },
    }"
    x-init="
        const mq = window.matchMedia('(max-width: 1023px)')
        const onChange = () => syncViewport()
        if (mq.addEventListener) {
            mq.addEventListener('change', onChange)
        } else {
            mq.addListener(onChange)
        }
    "
    x-cloak
>
    {{-- Desktop: inline banner --}}
    <div
        class="tido-auth-login-toast"
        x-show="showInline"
        x-cloak
    >
        <span class="tido-auth-login-toast-icon" aria-hidden="true">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
        </span>

        <div class="tido-auth-login-toast-body">
            <p class="tido-auth-login-toast-title">{{ $toastTitle }}</p>
            <p class="tido-auth-login-toast-description">
                {{ $toastDescription }}
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
            x-on:click="dismiss()"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
        </button>
    </div>

    {{-- Mobile: modal on phone / password only (not otp 6-digit step) --}}
    <div
        class="tido-auth-login-toast-modal"
        x-show="showModal"
        x-cloak
        x-transition.opacity.duration.200ms
        role="dialog"
        aria-modal="true"
        aria-labelledby="tido-auth-login-toast-modal-title"
    >
        <div
            class="tido-auth-login-toast-modal-backdrop"
            x-on:click="dismiss()"
        ></div>

        <div class="tido-auth-login-toast-modal-panel">
            <span class="tido-auth-login-toast-icon" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
            </span>

            <div class="tido-auth-login-toast-body">
                <p
                    id="tido-auth-login-toast-modal-title"
                    class="tido-auth-login-toast-title"
                >
                    {{ $toastTitle }}
                </p>
                <p class="tido-auth-login-toast-description">
                    {{ $toastDescription }}
                </p>
            </div>

            <button
                type="button"
                class="tido-auth-login-toast-close"
                aria-label="Dismiss"
                x-tooltip="{
                    content: @js('Dismiss'),
                    theme: $store.theme,
                    zIndex: 100000,
                }"
                x-on:click="dismiss()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    </div>
</div>
