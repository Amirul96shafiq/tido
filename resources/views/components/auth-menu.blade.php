<div class="fi-auth-menu">
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button
                aria-label="Auth menu"
                type="button"
                class="fi-auth-menu-trigger fi-user-menu-trigger"
                x-tooltip="{
                    content: @js('Auth menu'),
                    theme: $store.theme,
                }"
            >
                <img
                    src="{{ asset('images/tido-auth-menu-icon-d.png') }}"
                    alt="tido"
                    class="size-8 rounded-full dark:hidden"
                />
                <img
                    src="{{ asset('images/tido-auth-menu-icon-l.png') }}"
                    alt="tido"
                    class="size-8 rounded-full hidden dark:block"
                />
            </button>
        </x-slot>

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <x-filament::dropdown.list>
                <x-filament-panels::theme-switcher />
            </x-filament::dropdown.list>
        @endif

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                icon="heroicon-o-code-bracket"
                tag="button"
                x-on:click="window.showChangelogModal(); close()"
            >
                Changelogs
            </x-filament::dropdown.list.item>

            @if (\App\Models\User::query()->doesntExist())
                <x-filament::dropdown.list.item
                    icon="heroicon-o-arrow-path"
                    tag="button"
                    x-on:click="window.showRestoreBackupModal(); close()"
                >
                    Restore Backup
                </x-filament::dropdown.list.item>
            @endif
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
