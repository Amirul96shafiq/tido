<div
    x-tooltip="{
        content: @js(__('filament-panels::layout.actions.open_database_notifications.label')),
        theme: $store.theme,
    }"
    aria-label="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
    class="fi-version-icon-btn fi-topbar-database-notifications-btn"
>
    {{
        \Filament\Support\generate_icon_html(
            \Filament\Support\Icons\Heroicon::OutlinedBell,
            alias: \Filament\View\PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON,
            size: \Filament\Support\Enums\IconSize::Medium,
        )
    }}

    @if ($unreadNotificationsCount)
        <span @class([
            'absolute top-1 right-1 flex items-center justify-center',
            'h-4 min-w-4' => $unreadNotificationsCount < 10,
            'h-4 min-w-[1.125rem] px-0.5' => $unreadNotificationsCount >= 10,
        ])>
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900">
                {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
            </span>
        </span>
    @endif
</div>
