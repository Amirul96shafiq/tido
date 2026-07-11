@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div
        class="fi-auth-theme-switcher fixed top-4 end-4 z-50 rounded-lg bg-gray-50 p-1 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
        x-data="{ close() {} }"
    >
        <x-filament-panels::theme-switcher />
    </div>
@endif
