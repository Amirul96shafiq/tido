# UI section nav (sticky tabs + smooth scroll)

Shared sticky **in-page tab menu** for long Filament pages. Jumps between anchored sections with smooth scroll, active-tab sync, and hash-aware deep links.

**Consumers:** [`EditProfile`](../app/Filament/Pages/Auth/EditProfile.php) at `/admin/profile` and [`Dashboard`](../app/Filament/Pages/Dashboard.php) for widget jump tabs beside the month filter.

## Source of truth

| Layer | Path |
|-------|------|
| Tab UI + Alpine | [`resources/views/filament/schemas/components/section-nav.blade.php`](../resources/views/filament/schemas/components/section-nav.blade.php) |
| Shared nav CSS | `.tido-section-nav` in [`resources/css/app.css`](../resources/css/app.css) |
| SPA / global-search hash scroll | [`<x-hash-scroll />`](../resources/views/components/hash-scroll.blade.php) (panel render hook) |
| Global search sections | [`AdminDestinationSearch`](../app/Filament/GlobalSearch/AdminDestinationSearch.php) |

Sticky pin mechanics are shared with [`ui-sticky-blur.md`](ui-sticky-blur.md). This doc covers the **tab menu** behaviour on top of that pin.

## What it does

1. Renders Filament native tabs (`<x-filament::tabs>`) as `#section-id` links.
2. Sticks below the panel topbar via `tido-sticky-marker--top` inside `tido-sticky-scope`.
3. On tab click: `preventDefault` → `scrollIntoView({ behavior: 'smooth' })` → `history.replaceState` for the hash (avoids the browser’s instant jump).
4. Marks the active tab from `IntersectionObserver` while scrolling, and from the URL hash / `open-section` window events.
5. Hides the native horizontal scrollbar on overflow tabs; shows left/right gradient fades when more tabs are off-screen (`updateScrollHints`, `tido-section-nav--can-scroll-left` / `--can-scroll-right`).
6. Supports click-and-hold horizontal drag on the tab strip (`onTabPointerDown` / `onTabPointerMove` / `endTabDrag`); suppresses tab navigation when the pointer moved past the drag threshold (`dragMoved` guard in `onNavClick`). `setPointerCapture` is applied only after the threshold so a plain click still targets the `<a>` tab link. Native `<a>` drag is blocked (`dragstart` `preventDefault`, `draggable="false"`, `-webkit-user-drag: none`) so the browser does not cancel the pointer with `pointercancel`.

## Contract (do not invent a second pattern)

| Token | Role |
|-------|------|
| `.tido-section-nav` | Root wrapper + Alpine state |
| `--tido-section-nav-height` | Used in page `scroll-margin-top` so anchors clear the sticky tabs |
| `sections` view data | `list<array{label: string, id: string}>` passed to the Blade view |
| `ariaLabel` view data | Accessible label for the tab list (page-specific; default `Page sections`) |
| Stable anchor `id`s | Target anchors for tabs, hash scroll, global search |
| `scrollToSection` / `onNavClick` | Click intercept + smooth scroll (in the Blade Alpine `x-data`) |
| `<x-hash-scroll />` | Smooth scroll for SPA / global-search navigation to `#hash` |
| `open-section` CustomEvent | Hash scroll sets active tab; nav listens and updates `activeId` |
| `.tido-section-nav__frame` / `__fade` | Scroll frame + conditional edge gradient cues |
| `updateScrollHints` / `canScrollLeft` / `canScrollRight` | Alpine scroll overflow detection (scroll + `ResizeObserver`) |
| `onTabPointerDown` / `onTabPointerMove` / `endTabDrag` | Pointer drag-to-scroll on `.fi-tabs`; `setPointerCapture` only after `dragThreshold` (capturing on pointerdown retargets click to `<nav>` and breaks tab links) |
| `dragMoved` / `dragThreshold` | Suppress tab click after a real horizontal drag |
| `tido-section-nav--dragging` | Active while pointer is captured; `cursor: grabbing` + `user-select: none` |

## Profile

| Layer | Path |
|-------|------|
| Section list | `EditProfile::sectionNavItems()` |
| Sticky pin + form scope | `EditProfile::getFormContentComponent()` — `tido-sticky-scope` + `tido-sticky-marker--top` |
| Page class | `.fi-profile-page` from `EditProfile::getPageClasses()` |
| Scroll offset CSS | [`.fi-profile-page .fi-sc-section[id]`](../resources/css/app.css) |
| Tests | [`tests/Feature/ProfileSectionNavTest.php`](../tests/Feature/ProfileSectionNavTest.php) |

### Adding or renaming a profile section

1. Give the section a stable `->id('kebab-case')` on `Section::make(...)`.
2. Add / update the entry in `EditProfile::sectionNavItems()` (main column only — not the sidebar photo/details stack).
3. Register the section in `AdminDestinationSearch` if it should appear in global search (`url` = profile URL + `#id`).
4. Extend `tests/Feature/ProfileSectionNavTest.php` (and global-search tests when searchable).
5. Keep `scroll-margin-top` on `.fi-profile-page .fi-sc-section[id]` — do not remove it when changing tab height; update `--tido-section-nav-height` instead.

### Profile-only rules

- Leave sidebar-only blocks (Profile Photo, Personal Details) out of the tab list.
- Profile sidebar sticky offset uses `--tido-section-nav-height` in `.fi-profile-page .fi-sc-form .fi-grid-col:has(.fi-profile-sidebar-sticky)`.

## Dashboard

| Layer | Path |
|-------|------|
| Section list | `Dashboard::widgetNavItems()` |
| Widget anchor ids | `HasDashboardSectionId` on widgets |
| Sticky toolbar | `.tido-dashboard-sticky-toolbar` + `.tido-dashboard-sticky-toolbar-nav` |
| Page class | `.tido-dashboard-page` |
| Scroll offset CSS | [`.tido-dashboard-page .fi-wi-widget[id]`](../resources/css/app.css) + `--tido-dashboard-section-nav-offset` |
| Tests | [`tests/Feature/DashboardSectionNavTest.php`](../tests/Feature/DashboardSectionNavTest.php) |

## Smooth scroll vs hash-scroll

| Entry path | Who scrolls |
|------------|-------------|
| Sticky tab click | Alpine `scrollToSection` in `section-nav` (`preventDefault` + `replaceState`) |
| Global search / external `#hash` / SPA navigate | `<x-hash-scroll />` (`hashchange` / `livewire:navigated`) |

Do **not** rely on bare `<a href="#id">` alone for in-page tabs — the browser jumps instantly and fights smooth scroll. Tab clicks must go through `onNavClick`.

## Checklist for reuse on another long page

1. Page class for scoped CSS (like `fi-profile-page` or `tido-dashboard-page`).
2. Sticky top marker + `View::make('filament.schemas.components.section-nav')` with `sections` and `ariaLabel`.
3. Stable section `id`s matching the nav list.
4. `scroll-margin-top` that accounts for topbar **and** sticky nav height.
5. Click intercept with `behavior: 'smooth'` + hash `replaceState`.
6. Optional: register sections in `AdminDestinationSearch` + rely on panel `<x-hash-scroll />`.

## Do not

- Call Filament SPA navigate on tab hashes (`spa-mode="false"` on tab items).
- Duplicate a second hash-scroll script — extend `<x-hash-scroll />` for cross-page / SPA entry, keep tab click handling in the nav Alpine.
- Drop `scroll-margin-top` — sections will hide under the sticky tabs.
- Invent a second tab-nav pattern — reuse `section-nav.blade.php`.
