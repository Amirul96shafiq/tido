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

| Slot   | Action                                                                                                                        | Icon (idle)               | Icon (active)                                 |
| ------ | ----------------------------------------------------------------------------------------------------------------------------- | ------------------------- | --------------------------------------------- |
| Home   | Dashboard (`wire:navigate`)                                                                                                   | `OutlinedHome`            | solid `Home` on any dashboard view (`/admin`) |
| Menu   | Toggle `$store.sidebar` (mobile sidebar)                                                                                      | `OutlinedBars3`           | `OutlinedBars3BottomLeft` while sidebar open  |
| Add    | Sheet above the bar — Finances: Add Receipt, Add Budget, Add Recurring (tap again to close)                                   | `OutlinedPlusCircle`      | solid `PlusCircle` while Add sheet open       |
| Search | `open-global-search-modal` event (Global Search Modal)                                                                        | `OutlinedMagnifyingGlass` | —                                             |
| Avatar | Dedicated user menu instance (`instance="mobilenav"`) — Profile, Notifications, Logout, switcher, theme, calendar, changelogs | avatar                    | —                                             |

Home active state uses `wire:current.exact` on the dashboard link (path `/admin`; query `?view=` does not affect match). Menu and Add swap icons via Alpine (`$store.sidebar.isOpen`, `addOpen`).

Family members: Add Receipt stays available; Add Budget / Add Recurring render disabled with the primary-only CTA message.

## User menu anchor

The bottom avatar is a **second** user-menu instance — not a proxy of the hidden topbar trigger.

- Component: [`user-menu.blade.php`](../resources/views/vendor/filament-panels/components/user-menu.blade.php) with `instance="mobilenav"` and `anchor="mobilenav"`
- Dropdown: `placement="top-end"`, `offset={13}`, `shift={true}`, `sizePadding={0}`, `teleport=true` — panel bottom aligns with the nav bar top (compensates for vertically centered avatar trigger); inset from the screen edge by `--tido-mobilenav-inset` (0.75rem)
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

## Chrome offsets (mobile + `html.tido-mobilenav`)

- `--tido-mobilenav-height: calc(4rem + 1px + env(safe-area-inset-bottom, 0px))`
- `--tido-mobilenav-menu-gap: 0.75rem` — shared offset above the nav bar for Add sheet and user menu panel
- Main content `padding-bottom` clears the bar plus `0.25rem` gap (matches sticky CTAs / go-to-bottom)
- Page vertical scroll is confined to `.fi-main-ctn` (`height: calc(100dvh - var(--tido-mobilenav-height))`, `overflow-y: auto`) so the page scrollbar ends above the fixed bar; `html` / `body` / `.fi-body` use `overflow: hidden`
- `.fi-body::after` scrollbar rail stops above the bar (`inset-block-end: var(--tido-mobilenav-height)`)
- Table inner-scroll caps (`.fi-ta-ctn`, `.fi-ta-content-ctn`) subtract `--tido-mobilenav-height` so horizontal overflow thumbs stay above the bar
- Open sidebar is half the viewport (`50vw` / `--tido-mobilenav-sidebar-width`); `inset-block-end` + `height: auto` sits above the bar
- Collapse footer and Collapse Sidebar CTA are hidden; the Menu slot closes the drawer
- Sticky form CTAs (`tido-sticky-marker--bottom`) and bottom blur veil sit above the nav
- Top sticky bars pin at `0.25rem` (no topbar offset)
- Hash / section-nav scroll margins drop the 71px topbar offset
- Go-to-bottom CTA sits above the nav; go-to-top uses viewport top

## Tests

[`MobileNavProfileTest.php`](../tests/Feature/MobileNavProfileTest.php)

## Related

- [ui-reduce-motion.md](ui-reduce-motion.md) — sibling Profile preference pattern
- [ui-sticky-blur.md](ui-sticky-blur.md) — bottom sticky form CTAs
- [ui-tooltips.md](ui-tooltips.md) — `aria-label` on icon slots (Tippy off below `sm`)
- [household-access.md](household-access.md) — family member Add Budget / Recurring ACL
