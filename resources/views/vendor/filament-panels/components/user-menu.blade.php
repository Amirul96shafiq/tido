@props([
    'position' => null,
    'instance' => 'topbar',
    'anchor' => null,
])

@php
    use App\Helpers\GitHelper;
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

    $anchor ??= $instance === 'mobilenav'
        ? 'mobilenav'
        : (($position === UserMenuPosition::Topbar) ? 'topbar' : 'sidebar');

    $dropdownPlacement = match ($anchor) {
        'mobilenav' => 'top-end',
        'topbar' => 'bottom-end',
        default => 'top-end',
    };

    $dropdownOffset = match ($anchor) {
        'mobilenav' => 28,
        'topbar' => -39,
        default => 8,
    };

    $dropdownShift = $anchor === 'mobilenav';

    $dropdownSizePadding = match ($anchor) {
        'mobilenav' => 16,
        default => 16,
    };

    $dropdownTeleport = match ($anchor) {
        'mobilenav' => false,
        default => true,
    };

    $isAvatarTrigger = in_array($anchor, ['topbar', 'mobilenav'], true);

    $userMenuInstanceKey = str_replace('-', '_', $instance);

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    $gitVersion = GitHelper::getVersionString();
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="$dropdownPlacement"
    :offset="$dropdownOffset"
    :shift="$dropdownShift"
    :sizePadding="$dropdownSizePadding"
    :teleport="$dropdownTeleport"
    :useModalTransition="$anchor === 'mobilenav'"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class([
                'fi-user-menu',
                'fi-user-menu--' . $instance,
            ])
    "
>
    <x-slot name="trigger">
        @if ($isAvatarTrigger)
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
                @if ($anchor === 'mobilenav')
                    x-bind:class="{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': $store.tidoNotifications?.menuOpen || @js(request()->routeIs('filament.admin.pages.auth.edit-profile') || request()->is('*/profile*')) }"
                    x-on:click.capture="! $store.tidoNotifications?.menuOpen && $store.tidoMobileChrome?.primeOverlay()"
                @endif
                x-data
                x-init="
                    if (! Alpine.store('tidoNotifications')) {
                        Alpine.store('tidoNotifications', { unread: 0, menuOpen: false });
                    } else if (Alpine.store('tidoNotifications').menuOpen === undefined) {
                        Alpine.store('tidoNotifications').menuOpen = false;
                    }

                    // Filament 5.7+: aria-expanded lives on the focusable button; the
                    // .fi-dropdown-trigger wrapper attribute is stripped by dropdown.js.
                    // Observe the panel display/transition too so menuOpen does not flicker
                    // false during mobilenav modal transitions (shared chrome overlay).
                    const expandTarget = $el;
                    const dropdown = expandTarget.closest('.fi-dropdown');
                    const panel = dropdown ? dropdown.querySelector('.fi-dropdown-panel') : null;

                    const syncMenuOpen = () => {
                        const isExpanded = expandTarget.getAttribute('aria-expanded') === 'true';

                        if (!panel) {
                            Alpine.store('tidoNotifications').menuOpen = isExpanded;

                            return;
                        }

                        // Keep menuOpen through enter/leave so the shared chrome overlay
                        // does not flicker. Do not treat bare panel display as open —
                        // Livewire action unmount morphs can flash display and resurrect
                        // menuOpen after Sign out Cancel (overlay + Profile active stuck).
                        const isTransitioning = panel.classList.contains('fi-transition-enter')
                            || panel.classList.contains('fi-transition-enter-start')
                            || panel.classList.contains('fi-transition-enter-end')
                            || panel.classList.contains('fi-transition-leave')
                            || panel.classList.contains('fi-transition-leave-start')
                            || panel.classList.contains('fi-transition-leave-end');

                        Alpine.store('tidoNotifications').menuOpen =
                            isExpanded || isTransitioning;
                    };

                    syncMenuOpen();

                    new MutationObserver(syncMenuOpen).observe(expandTarget, {
                        attributes: true,
                        attributeFilter: ['aria-expanded'],
                    });

                    if (panel) {
                        new MutationObserver(syncMenuOpen).observe(panel, {
                            attributes: true,
                            attributeFilter: ['style', 'class'],
                        });
                    }
                "
                x-tooltip="{
                    content: @js(__('filament-panels::layout.actions.open_user_menu.label')),
                    theme: $store.theme,
                }"
            >
                @if ($anchor === 'mobilenav')
                    <span class="fi-user-menu-avatar-wrap">
                        <span
                            x-cloak
                            x-show="! ($store.tidoNotifications?.menuOpen || @js(request()->routeIs('filament.admin.pages.auth.edit-profile') || request()->is('*/profile*')))"
                        >
                            {{
                                \Filament\Support\generate_icon_html(
                                    \Filament\Support\Icons\Heroicon::OutlinedUser,
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                                )
                            }}
                        </span>
                        <span
                            x-cloak
                            x-show="$store.tidoNotifications?.menuOpen || @js(request()->routeIs('filament.admin.pages.auth.edit-profile') || request()->is('*/profile*'))"
                        >
                            {{
                                \Filament\Support\generate_icon_html(
                                    \Filament\Support\Icons\Heroicon::User,
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon text-primary-600 dark:text-primary-400']),
                                )
                            }}
                        </span>

                        <span
                            x-cloak
                            x-show="$store.tidoNotifications.unread > 0 && ! $store.tidoNotifications.menuOpen"
                            x-bind:class="{
                                'h-4 min-w-4': $store.tidoNotifications.unread < 10,
                                'h-4 min-w-[1.125rem] px-0.5': $store.tidoNotifications.unread >= 10,
                            }"
                            class="fi-user-menu-notifications-badge flex items-center justify-center"
                        >
                            <span class="tido-ping-pulse absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span
                                class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900"
                                x-text="$store.tidoNotifications.unread > 99 ? '99+' : $store.tidoNotifications.unread"
                            ></span>
                        </span>
                    </span>
                    <span
                        class="tido-mobilenav-label"
                        x-bind:class="{ 'text-primary-600 dark:text-primary-400': $store.tidoNotifications?.menuOpen || @js(request()->routeIs('filament.admin.pages.auth.edit-profile') || request()->is('*/profile*')) }"
                    >Profile</span>
                @else
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
                            <span class="tido-ping-pulse absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span
                                class="relative inline-flex h-full min-w-full items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-semibold leading-none text-zinc-900"
                                x-text="$store.tidoNotifications.unread > 99 ? '99+' : $store.tidoNotifications.unread"
                            ></span>
                        </span>
                    </span>
                @endif
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
                            <span class="tido-ping-pulse absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
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

    @livewire(\App\Filament\Livewire\AccountSwitcher::class, key('account-switcher-'.$userMenuInstanceKey))

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
                            <span class="tido-ping-pulse absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
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

    <div class="fi-user-menu-version-footer">
        <span class="fi-user-menu-version-footer-label"> - tido App - </span>
        <span class="fi-user-menu-version-footer-version">{{ $gitVersion }}</span>
    </div>
</x-filament::dropdown>

@if ($anchor !== 'mobilenav')
    <div
        x-cloak
        x-data
        x-show="$store.tidoNotifications && $store.tidoNotifications.menuOpen"
        x-transition.duration.300ms.opacity
        x-on:click="Alpine.$data($el.previousElementSibling)?.close?.()"
        class="tido-chrome-overlay tido-user-menu-overlay lg:hidden"
        aria-hidden="true"
    ></div>
@endif

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
