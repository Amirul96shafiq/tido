@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    @php
        $defaultTheme = filament()->getDefaultThemeMode()->value;
    @endphp

    <div
        data-field-wrapper
        class="fi-fo-field fi-fo-field-has-inline-label"
        x-data="{
            mode: localStorage.getItem('theme') || @js($defaultTheme),
            label() {
                return { light: 'Light', dark: 'Dark', system: 'System' }[this.mode] ?? 'System';
            },
        }"
        x-on:theme-changed.window="mode = $event.detail"
    >
        <div class="fi-fo-field-label-col fi-vertical-align-center">
            <div class="fi-fo-field-label-ctn">
                <label class="fi-fo-field-label">
                    <span class="fi-fo-field-label-content">Theme Mode</span>
                </label>
            </div>
        </div>

        <div class="fi-fo-field-content-col">
            <div class="tido-theme-mode-field flex w-full items-center justify-between gap-2">
                <x-filament-panels::theme-switcher />

                <span class="rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
                    <span x-text="label()">System</span>
                </span>
            </div>
        </div>
    </div>
@endif
