# Mobile Navigation Menu (opt-in bottom bar)

Profile **Mobile Navigation Menu** replaces the sticky Filament topbar with a five-slot bottom navigation bar on viewports below `lg` (1024px).

## Profile control

- Field: `users.mobile_nav_enabled` (boolean, default `false`)
- UI: custom Blade field in **Personalize & Appearance → APPEARANCES** on [`EditProfile.php`](../app/Filament/Pages/Auth/EditProfile.php) — `Hidden::make('mobile_nav_enabled')` + [`mobile-nav-field.blade.php`](../resources/views/filament/schemas/components/mobile-nav-field.blade.php)
- Helper: [`App\Support\MobileNav`](../app/Support/MobileNav.php)
- Pills: `Enabled: Bottom Bar` / `Disabled: Top Bar`
- Helper text: _Save changes needed to take effect._
- Preview: portrait `tido-mobilenav-preview` phone mock via [`mobile-preview-chrome`](../resources/views/components/tido/mobile-preview-chrome.blade.php) (no sidebar; top bar vs five-slot bottom bar)

When enabled, the panel adds `html.tido-mobilenav` and hides `.fi-topbar-ctn` below `lg`. The bottom bar root uses `.tido-mobilenav-root` so the document flag does not share a class with `display: none`. In-page Filament headers (breadcrumbs, titles, dashboard Focus tabs) stay visible.

## Bottom bar slots

| Slot    | Label   | Action                                                                                                                        | Icon (idle)               | Icon (active)                                      |
| ------- | ------- | ----------------------------------------------------------------------------------------------------------------------------- | ------------------------- | -------------------------------------------------- |
| Home    | Home    | Dashboard (`wire:navigate`)                                                                                                   | `OutlinedHome`            | solid `Home` on any dashboard view (`/admin`)      |
| Menu    | Menu    | Toggle `$store.sidebar` (mobile sidebar)                                                                                      | `OutlinedBars3`           | `OutlinedBars3BottomLeft` while sidebar open       |
| Add     | Add     | Sheet above the bar — Finances: Add Receipt, Add Budget, Add Recurring (tap again to close)                                   | `Plus` (floating primary button, 0deg) | `Plus` rotated 45deg to `×` while Add sheet open   |
| Search  | Search  | `open-global-search-modal` event (Global Search Modal)                                                                        | `OutlinedMagnifyingGlass` | —                                                  |
| Profile | Profile | Dedicated user menu instance (`instance="mobilenav"`) — Profile, Notifications, Logout, switcher, theme, calendar, changelogs | `OutlinedUser`            | solid `User` while user menu open or on Profile    |

Each slot includes a text label (`.tido-mobilenav-label`) positioned below its icon, horizontally aligned straight across all 5 slots from the bottom edge of the bar (`padding-bottom: 0.5rem`). Home active state uses `wire:current.exact` on the dashboard link (path `/admin`; query `?view=` does not affect match). Menu swaps icons via Alpine (`$store.sidebar.isOpen`), Add rotates smoothly to `×` via CSS transition on Alpine `$store.tidoMobileChrome.addOpen`, and Profile swaps outline/solid based on menu state and Profile page route.

Family members: Add Receipt stays available; Add Budget / Add Recurring render disabled with the primary-only CTA message.

## User menu anchor

The bottom user icon is a **second** user-menu instance — not a proxy of the hidden topbar trigger.

- Component: [`user-menu.blade.php`](../resources/views/vendor/filament-panels/components/user-menu.blade.php) with `instance="mobilenav"` and `anchor="mobilenav"`
- Trigger: centered outline/solid user icon (replaces desktop avatar picture for mobile nav)
- Dropdown: `placement="top-end"`, `offset={28}`, `shift={true}`, `sizePadding={16}`, `teleport=false` — equal 1.75rem (28px) gap from right screen edge and above the nav bar
- Account switcher Livewire key: `account-switcher-mobilenav`

The topbar user menu remains mounted for desktop and for `DatabaseNotifications` Livewire.

## Livewire + assets

| Layer        | Path                                                                                                                                 |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| Component    | [`MobileNav.php`](../app/Filament/Livewire/MobileNav.php)                                                                            |
| View         | [`mobile-nav.blade.php`](../resources/views/filament/livewire/mobile-nav.blade.php)                                                  |
| Registration | [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php) `BODY_END` hook                                             |
| Client API   | `window.tidoSetMobileNav(bool)` + `sessionStorage` key `tidoMobileNav` (SPA restore on `livewire:navigating` / `livewire:navigated`) |
| CSS          | `.tido-mobilenav` rules in [`app.css`](../resources/css/app.css)                                                                     |

Dark bar chrome uses `html.dark.tido-mobilenav` (Filament puts `dark` on `<html>`, same as `.dark .fi-topbar`). Do not write `.dark html.tido-mobilenav` — that selector never matches.

## Chrome overlays (below `lg`)

When `html.tido-mobilenav` is active, **one shared dim layer** covers the page while any bottom-bar chrome surface is open (Menu sidebar, Add sheet, user menu, or global search). The overlay fades in when the first surface opens and fades out only when all are closed — switching slots does not refresh the dim layer.

Markup: [`mobile-chrome-overlay.blade.php`](../resources/views/components/tido/mobile-chrome-overlay.blade.php) included from [`panel-body-end.blade.php`](../resources/views/components/panel-body-end.blade.php) (end of `<body>`, before the bottom nav). Classes: `.tido-mobilenav-shared-chrome-overlay.tido-chrome-overlay` (plus Filament’s `.fi-sidebar-close-overlay` for stacking). State: Alpine store `tidoMobileChrome` (`addOpen`, `searchOpen`, `overlayOpen`, `overlayShown`, `syncOverlay()`, `closeActiveChrome()`).

Visual recipe matches Filament’s sidebar close overlay: `bg-gray-950/50` / `dark:bg-gray-950/75`, **no** `backdrop-blur`.

| Surface        | Panel / sheet                         | Per-surface overlay (mobilenav)                           |
| -------------- | ------------------------------------- | --------------------------------------------------------- |
| Sidebar (Menu) | Filament `.fi-sidebar`                | Hidden — uses shared overlay                              |
| Add sheet      | `.tido-mobilenav-add-sheet`           | Hidden — uses shared overlay                              |
| User menu      | `.fi-user-menu--mobilenav` dropdown   | Hidden — uses shared overlay                              |
| Global search  | `#global-search-modal::plugin` window | Hidden (`display: none !important`) — uses shared overlay |

Topbar user menu (non-mobilenav) keeps its own `.tido-user-menu-overlay` in [`user-menu.blade.php`](../resources/views/vendor/filament-panels/components/user-menu.blade.php) (`lg:hidden`).

Shared chrome overlay uses `z-index: 29` under `html.tido-mobilenav` (above `.fi-main-ctn`, below `.fi-sidebar` at `z-30` and bottom bar at `65`) so the dim shows beside the Menu drawer while chrome sheets / the bar stay above the dim. When a modal is open, `.fi-main-ctn` stays at `z-index: 1` so it does not stack above the shared dim.

**Changelogs** stays full viewport height (`h-dvh`). Opening it closes the user menu and lifts the slide-over (`z-index: calc(var(--tido-mobilenav-z-chrome) + 1)`) so the sheet covers the bottom bar. Global search still offsets `bottom` above the bar instead.

**Notifications** (database inbox) mounts from [`panel-body-end.blade.php`](../resources/views/components/panel-body-end.blade.php), not the hidden `.fi-topbar-ctn`. The slide-over uses the same z-index lift as Changelogs. The unread badge still syncs through the sr-only trigger inside that Livewire view.

## Chrome offsets (mobile + `html.tido-mobilenav`)

- `--tido-mobilenav-height: calc(4rem + 1px + env(safe-area-inset-bottom, 0px))`
- `--tido-mobilenav-menu-gap: 0.75rem` — shared offset above the nav bar for Add sheet and user menu panel
- Main content `padding-bottom` is `0.25rem` inside `.fi-main-ctn` (the scrollport already excludes the bar)
- Page vertical scroll is confined to `.fi-main-ctn` (`height: calc(100dvh - var(--tido-mobilenav-height))`, `overflow-y: auto`) so the page scrollbar ends above the fixed bar; `html` / `body` / `.fi-body` use `overflow: hidden`
- `.fi-body::after` scrollbar rail stops above the bar (`inset-block-end: var(--tido-mobilenav-height)`)
- Table inner-scroll caps (`.fi-ta-ctn`, `.fi-ta-content-ctn`) subtract `--tido-mobilenav-height` so horizontal overflow thumbs stay above the bar
- Open sidebar is half the viewport (`50vw` / `--tido-mobilenav-sidebar-width`); `inset-block-end` + `height: auto` sits above the bar
- Collapse footer and Collapse Sidebar CTA are hidden; the Menu slot closes the drawer
- Navigation groups below `lg` start fully expanded when the Menu drawer opens (`collapsedGroups = []`); multiple groups may stay open; users can still collapse individual groups
- Sticky form CTAs pin at `0.25rem` above the `.fi-main-ctn` bottom (do not add `--tido-mobilenav-height` — sticky is relative to the already-shortened scrollport). The `position: fixed` blur veil still uses `inset-block-end: var(--tido-mobilenav-height)`
- Top sticky bars pin at `0.25rem` (no topbar offset)
- Hash / section-nav scroll margins drop the 71px topbar offset
- Go-to-bottom CTA sits above the nav; go-to-top uses viewport top

## Sidebar groups (mobile)

Below `lg`, the published sidebar preload in [`sidebar.blade.php`](../resources/views/vendor/filament-panels/livewire/sidebar.blade.php) expands every navigation group before Alpine mounts. The Menu slot in [`mobile-nav.blade.php`](../resources/views/filament/livewire/mobile-nav.blade.php) clears `$store.sidebar.collapsedGroups` each time the drawer opens so Finances / Settings / Integrations / Tools all start expanded. Filament’s native per-group collapse toggle still works; multiple groups may stay open at once.

## Tests

[`MobileNavProfileTest.php`](../tests/Feature/MobileNavProfileTest.php)

## Related

- [ui-reduce-motion.md](ui-reduce-motion.md) — sibling Profile preference pattern
- [ui-sticky-blur.md](ui-sticky-blur.md) — bottom sticky form CTAs
- [ui-tooltips.md](ui-tooltips.md) — `aria-label` on icon slots (Tippy off below `sm`)
- [household-access.md](household-access.md) — family member Add Budget / Recurring ACL
