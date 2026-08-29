@php
    $redirectUri = app(\App\Services\GoogleOAuth\GoogleOAuthSettings::class)->redirectUrl();
@endphp

<div class="rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-slate-700">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="font-medium text-gray-950 dark:text-white">Redirect URI</p>
            <p class="mt-1 font-mono text-xs break-all text-gray-600 dark:text-gray-400">{{ $redirectUri }}</p>
        </div>
        <x-filament::button
            color="gray"
            size="sm"
            type="button"
            x-on:click="{{ \App\Support\ClipboardCopy::alpineClickHandler($redirectUri, 'Redirect URI copied.') }}"
        >
            Copy URI
        </x-filament::button>
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Add this exact URI to Authorized redirect URIs on the Web client. The value is derived from APP_URL.
    </p>
</div>
