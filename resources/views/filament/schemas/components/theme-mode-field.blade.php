@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div class="fi-fo-field flex flex-col gap-2">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
            Theme Mode
        </span>

        <div class="tido-theme-mode-field inline-flex">
            <x-filament-panels::theme-switcher />
        </div>
    </div>
@endif
