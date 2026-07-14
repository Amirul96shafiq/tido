<div class="fi-auth-menu fixed top-4 end-4 z-50">
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-auth-menu-trigger fi-user-menu-trigger"
                x-tooltip="{
                    content: @js(__('filament-panels::layout.actions.open_user_menu.label')),
                    theme: $store.theme,
                }"
            >
                <img
                    src="{{ asset('images/favicon.png') }}"
                    alt="tido"
                    class="size-8 rounded-lg"
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
