@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasChildFlyout = filled($childItems) && (! $subGrouped);
    $childFlyoutPlacement = (__('filament-panels::layout.direction') === 'rtl') ? 'left-start' : 'right-start';
    $childFlyoutChevron = (__('filament-panels::layout.direction') === 'rtl')
        ? \Filament\Support\Icons\Heroicon::ChevronLeft
        : \Filament\Support\Icons\Heroicon::ChevronRight;
    $flyoutId = $hasChildFlyout ? \Illuminate\Support\Str::slug(trim(strip_tags($slot->toHtml()))) : null;
    $childPaths = '';
    if ($hasChildFlyout) {
        $childPaths = collect($childItems)
            ->map(function (mixed $item): string {
                if (! is_object($item) || ! method_exists($item, 'getUrl')) {
                    return '';
                }

                $url = $item->getUrl();
                if (! is_string($url) || $url === '') {
                    return '';
                }

                $path = parse_url($url, PHP_URL_PATH);

                return is_string($path) && $path !== '' ? $path : $url;
            })
            ->filter()
            ->implode(' ');
    }
@endphp

<li
    {{
        $attributes
            ->class([
                'fi-sidebar-item',
                'fi-active' => $active || $activeChildItems,
                'fi-sidebar-item-has-active-child-items' => $activeChildItems,
                'fi-sidebar-item-has-url' => filled($url),
                'fi-sidebar-item-has-children' => $hasChildFlyout,
            ])
            ->merge(array_filter([
                'data-tido-flyout-id' => $flyoutId,
                'data-tido-child-paths' => $childPaths !== '' ? $childPaths : null,
            ]))
    }}
>
    @if ($hasChildFlyout)
        <x-filament::dropdown
            :placement="$childFlyoutPlacement"
            x-init="
                $nextTick(() => {
                    const panel = $refs.panel
                    const sidebar = $el.closest('.fi-sidebar')
                    if (! panel || ! sidebar || panel.dataset.tidoPortaled === '1') {
                        return
                    }
                    panel.dataset.tidoPortaled = '1'
                    panel.classList.add('tido-sidebar-flyout-panel')
                    const parentItem = $el.closest('.fi-sidebar-item')
                    if (parentItem && parentItem.dataset.tidoFlyoutId) {
                        panel.dataset.tidoFlyoutId = parentItem.dataset.tidoFlyoutId
                    }
                    sidebar.appendChild(panel)
                    if (! panel._tidoFlyoutHoverBound) {
                        panel._tidoFlyoutHoverBound = true
                        panel.addEventListener('mouseenter', () => clearTimeout($el._hideT))
                        panel.addEventListener('mouseleave', (event) => {
                            if (window.matchMedia('(min-width: 1024px)').matches) {
                                $el._hideT = setTimeout(() => close(event), 180)
                            }
                        })
                    }
                })
            "
            x-on:mouseenter="
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    clearTimeout($el._hideT)
                    open($event)
                }
            "
            x-on:mouseleave="
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    $el._hideT = setTimeout(() => close($event), 180)
                }
            "
            class="fi-sidebar-item-flyout"
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-sidebar-item-btn"
                >
                    @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
                        {{
                            \Filament\Support\generate_icon_html(($activeChildItems && $activeIcon) ? $activeIcon : $icon, attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                        }}
                    @endif

                    <span class="fi-sidebar-item-label">
                        {{ $slot }}
                    </span>

                    {{
                        \Filament\Support\generate_icon_html($childFlyoutChevron, attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-sidebar-item-flyout-chevron']), size: \Filament\Support\Enums\IconSize::Small)
                    }}
                </button>
            </x-slot>

            <x-filament::dropdown.header
                x-show="! $store.sidebar.isOpen"
                x-cloak
            >
                {{ $slot }}
            </x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($childItems as $childItem)
                    @php
                        $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                        $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                        $childItemBadge = $childItem->getBadge();
                        $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                        $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                        $childItemIcon = $isChildActive
                            ? ($childItem->getActiveIcon() ?? $childItem->getIcon())
                            : $childItem->getIcon();
                        $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                        $childItemUrl = $childItem->getUrl();
                        $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                    @endphp

                    <x-filament::dropdown.list.item
                        :badge="$childItemBadge"
                        :badge-color="$childItemBadgeColor"
                        :badge-tooltip="$childItemBadgeTooltip"
                        :color="$isChildActive ? 'primary' : 'gray'"
                        :href="$childItemUrl"
                        :icon="$childItemIcon"
                        tag="a"
                        :target="$shouldChildItemOpenUrlInNewTab ? '_blank' : null"
                        :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)->class(['fi-active' => $isChildActive])->merge(['aria-current' => $isChildActive ? 'page' : null])"
                    >
                        {{ $childItem->getLabel() }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @else
        <a
            {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
            x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
            @if ($sidebarCollapsible && (! $subNavigation))
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: @js($slot->toHtml()),
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            class="fi-sidebar-item-btn"
        >
            @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
                {{
                    \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Illuminate\View\ComponentAttributeBag([
                        'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                    ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                }}
            @endif

            @if ((blank($icon) && $grouped) || $subGrouped)
                <div
                    @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-sidebar-item-grouped-border"
                >
                    @if (! $first)
                        <div
                            class="fi-sidebar-item-grouped-border-part-not-first"
                        ></div>
                    @endif

                    @if (! $last)
                        <div
                            class="fi-sidebar-item-grouped-border-part-not-last"
                        ></div>
                    @endif

                    <div class="fi-sidebar-item-grouped-border-part"></div>
                </div>
            @endif

            <span class="fi-sidebar-item-label">
                {{ $slot }}
            </span>

            @if (filled($badge))
                <span class="fi-sidebar-item-badge-ctn">
                    <x-filament::badge
                        :color="$badgeColor"
                        :tooltip="$badgeTooltip"
                    >
                        {{ $badge }}
                    </x-filament::badge>
                </span>
            @endif
        </a>

        @if ($childItems && (blank($url) || $active || $activeChildItems))
            <ul class="fi-sidebar-sub-group-items">
                @foreach ($childItems as $childItem)
                    @php
                        $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                        $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                        $childItemActiveIcon = $childItem->getActiveIcon();
                        $childItemBadge = $childItem->getBadge();
                        $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                        $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                        $childItemIcon = $childItem->getIcon();
                        $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                        $childItemUrl = $childItem->getUrl();
                        $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                    @endphp

                    <x-filament-panels::sidebar.item
                        :active="$isChildActive"
                        :active-child-items="$isChildItemChildItemsActive"
                        :active-icon="$childItemActiveIcon"
                        :badge="$childItemBadge"
                        :badge-color="$childItemBadgeColor"
                        :badge-tooltip="$childItemBadgeTooltip"
                        :first="$loop->first"
                        grouped
                        :icon="$childItemIcon"
                        :last="$loop->last"
                        :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                        sub-grouped
                        :sub-navigation="$subNavigation"
                        :url="$childItemUrl"
                        :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                    >
                        {{ $childItem->getLabel() }}
                    </x-filament-panels::sidebar.item>
                @endforeach
            </ul>
        @endif
    @endif
</li>
