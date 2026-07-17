@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Illuminate\Support\Arr;

    $user = filament()->auth()->user();

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="($position === UserMenuPosition::Topbar) ? 'bottom-end' : 'top-end'"
    :offset="($position === UserMenuPosition::Topbar) ? -39 : 8"
    :teleport="$position === UserMenuPosition::Topbar"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        @if ($position === UserMenuPosition::Topbar)
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
                x-data
                x-init="
                    if (! Alpine.store('tidoNotifications')) {
                        Alpine.store('tidoNotifications', { unread: 0, menuOpen: false });
                    } else if (Alpine.store('tidoNotifications').menuOpen === undefined) {
                        Alpine.store('tidoNotifications').menuOpen = false;
                    }

                    const dropdownTrigger = $el.closest('.fi-dropdown-trigger');

                    if (dropdownTrigger) {
                        const syncMenuOpen = () => {
                            Alpine.store('tidoNotifications').menuOpen =
                                dropdownTrigger.getAttribute('aria-expanded') === 'true';
                        };

                        syncMenuOpen();

                        new MutationObserver(syncMenuOpen).observe(dropdownTrigger, {
                            attributes: true,
                            attributeFilter: ['aria-expanded'],
                        });
                    }
                "
                x-tooltip="{
                    content: @js(__('filament-panels::layout.actions.open_user_menu.label')),
                    theme: $store.theme,
                }"
            >
                <span class="fi-user-menu-avatar-wrap">
                    <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                    <span
                        x-cloak
                        x-show="$store.tidoNotifications.unread > 0 && ! $store.tidoNotifications.menuOpen"
                        x-bind:class="{
                            'h-4 min-w-4': $store.tidoNotifications.unread < 10,
                            'h-4 min-w-[1.125rem] px-0.5': $store.tidoNotifications.unread >= 10,
                        }"
                        class="fi-user-menu-notifications-badge flex items-center justify-center"
                    >
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span
                            class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900"
                            x-text="$store.tidoNotifications.unread > 99 ? '99+' : $store.tidoNotifications.unread"
                        ></span>
                    </span>
                </span>
            </button>
        @else
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
                x-tooltip="{
                    content: @js(__('filament-panels::layout.actions.open_user_menu.label')),
                    theme: $store.theme,
                }"
            >
                <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                <span class="fi-user-menu-trigger-text">
                    {{ filament()->getUserName($user) }}
                </span>

                {{
                    \Filament\Support\generate_icon_html(
                        \Filament\Support\Icons\Heroicon::ChevronUp,
                        alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class([
                            'fi-user-menu-trigger-chevron',
                        ]),
                    )
                }}
            </button>
        @endif
    </x-slot>

    @if ($hasProfileHeader)
        @php
            $item = $itemsBeforeThemeSwitcher['profile'];
            $itemColor = $item->getColor();
            $itemIcon = $item->getIcon();

            unset($itemsBeforeThemeSwitcher['profile']);
        @endphp

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
            {{ $item->getLabel() }}
        </x-filament::dropdown.header>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    @if ($itemsBeforeThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsBeforeThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @elseif ($key === 'notifications')
                    <div class="fi-user-menu-notifications-wrap">
                        {{ $item }}

                        <span
                            x-cloak
                            x-show="$store.tidoNotifications.unread > 0 && $store.tidoNotifications.menuOpen"
                            x-bind:class="{
                                'h-4 min-w-4': $store.tidoNotifications.unread < 10,
                                'h-4 min-w-[1.125rem] px-0.5': $store.tidoNotifications.unread >= 10,
                            }"
                            class="fi-user-menu-item-notifications-badge flex items-center justify-center"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span
                                class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900"
                                x-text="$store.tidoNotifications.unread > 99 ? '99+' : $store.tidoNotifications.unread"
                            ></span>
                        </span>
                    </div>
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    <x-user-menu-profile-preview :user="$user" />

    @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
        <x-filament::dropdown.list>
            <x-filament-panels::theme-switcher />
        </x-filament::dropdown.list>
    @endif

    @if ($itemsAfterThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsAfterThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @elseif ($key === 'notifications')
                    <div class="fi-user-menu-notifications-wrap">
                        {{ $item }}

                        <span
                            x-cloak
                            x-show="$store.tidoNotifications.unread > 0 && $store.tidoNotifications.menuOpen"
                            x-bind:class="{
                                'h-4 min-w-4': $store.tidoNotifications.unread < 10,
                                'h-4 min-w-[1.125rem] px-0.5': $store.tidoNotifications.unread >= 10,
                            }"
                            class="fi-user-menu-item-notifications-badge flex items-center justify-center"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span
                                class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900"
                                x-text="$store.tidoNotifications.unread > 99 ? '99+' : $store.tidoNotifications.unread"
                            ></span>
                        </span>
                    </div>
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
