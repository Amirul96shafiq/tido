<div class="tido-auth-google-sign-in-wrap">
    <div class="tido-auth-google-divider" aria-hidden="true">
        <span>or</span>
    </div>

    <button
        type="button"
        disabled
        aria-disabled="true"
        aria-label="Continue with Google — Coming Soon"
        class="tido-auth-google-sign-in-btn tido-auth-google-sign-in-btn--disabled fi-btn fi-size-md fi-color-gray opacity-50"
        x-tooltip="{
            content: @js('Coming Soon'),
            theme: $store.theme,
        }"
    >
        <x-filament::icon icon="icon-google-oauth" class="h-5 w-5 shrink-0" />
        <span>Continue with Google</span>
    </button>
</div>
