# Mobile Nav (opt-in bottom bar)

Profile **Mobile Nav** replaces the sticky Filament topbar with a five-slot bottom navigation bar on viewports below `lg` (1024px).

## Profile control

- Field: `users.mobile_nav_enabled` (boolean, default `false`)
- UI: native `Toggle::make('mobile_nav_enabled')` in **Personalize & Appearance → PREFERENCES** on [`EditProfile.php`](../app/Filament/Pages/Auth/EditProfile.php)
- Helper: [`App\Support\MobileNav`](../app/Support/MobileNav.php)

When enabled, the panel adds `html.tido-mobilenav` and hides `.fi-topbar-ctn` below `lg`. The bottom bar root uses `.tido-mobilenav-root` so the document flag does not share a class with `display: none`. In-page Filament headers (breadcrumbs, titles, dashboard Focus tabs) stay visible.

## Bottom bar slots

| Slot   | Action                                                                                                                        |
| ------ | ----------------------------------------------------------------------------------------------------------------------------- |
| Home   | Dashboard (`wire:navigate`)                                                                                                   |
| Menu   | Toggle `$store.sidebar` (mobile sidebar)                                                                                      |
| Add    | Sheet above the bar — Finances: Add Receipt, Add Budget, Add Recurring                                                        |
| Search | `open-global-search-modal` event (Global Search Modal)                                                                        |
| Avatar | Dedicated user menu instance (`instance="mobilenav"`) — Profile, Notifications, Logout, switcher, theme, calendar, changelogs |

Family members: Add Receipt stays available; Add Budget / Add Recurring render disabled with the primary-only CTA message.

## User menu anchor

The bottom avatar is a **second** user-menu instance — not a proxy of the hidden topbar trigger.

- Component: [`user-menu.blade.php`](../resources/views/vendor/filament-panels/components/user-menu.blade.php) with `instance="mobilenav"` and `anchor="mobilenav"`
- Dropdown: `placement="top-end"`, `offset={8}`, `teleport=true` — panel opens **above** the bottom avatar
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

- `--tido-mobilenav-height: calc(4rem + env(safe-area-inset-bottom, 0px))`
- Main content `padding-bottom` clears the bar
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
