# UI profile section nav (sticky tabs + smooth scroll)

Sticky **in-page tab menu** on Edit Profile that jumps between main-column sections with smooth scroll, active-tab sync, and hash-aware deep links.

**Canonical use:** [`EditProfile`](../app/Filament/Pages/Auth/EditProfile.php) at `/admin/profile`. Also used on [`Dashboard`](../app/Filament/Pages/Dashboard.php) for widget jump tabs beside the month filter.

## Source of truth

| Layer | Path |
|-------|------|
| Section list | `EditProfile::sectionNavItems()` |
| Sticky pin + form scope | `EditProfile::getFormContentComponent()` — `tido-sticky-scope` + `tido-sticky-marker--top` |
| Tab UI + Alpine | [`resources/views/filament/schemas/components/profile-section-nav.blade.php`](../resources/views/filament/schemas/components/profile-section-nav.blade.php) |
| Scroll offset CSS | [`.fi-profile-page .fi-sc-section[id]`](../resources/css/app.css) + `.tido-profile-section-nav` |
| SPA / global-search hash scroll | [`<x-hash-scroll />`](../resources/views/components/hash-scroll.blade.php) (panel render hook) |
| Global search sections | [`AdminDestinationSearch`](../app/Filament/GlobalSearch/AdminDestinationSearch.php) |
| Tests | [`tests/Feature/ProfileSectionNavTest.php`](../tests/Feature/ProfileSectionNavTest.php) |

Sticky pin mechanics are shared with [`ui-sticky-blur.md`](ui-sticky-blur.md). This doc covers the **tab menu** behaviour on top of that pin.

## What it does

1. Renders Filament native tabs (`<x-filament::tabs>`) as `#section-id` links.
2. Sticks below the panel topbar via `tido-sticky-marker--top` inside `tido-sticky-scope`.
3. On tab click: `preventDefault` → `scrollIntoView({ behavior: 'smooth' })` → `history.replaceState` for the hash (avoids the browser’s instant jump).
4. Marks the active tab from `IntersectionObserver` while scrolling, and from the URL hash / `open-section` window events.
5. Leaves sidebar-only blocks (Profile Photo, Personal Details) out of the tab list.

## Contract (do not invent a second pattern)

| Token | Role |
|-------|------|
| `.fi-profile-page` | Page class from `EditProfile::getPageClasses()` — scopes scroll-margin |
| `.tido-profile-section-nav` | Root wrapper + Alpine state |
| `--tido-profile-section-nav-height` | Used in section `scroll-margin-top` so anchors clear the sticky tabs |
| `EditProfile::sectionNavItems()` | Single list of `{ label, id }` for tabs (and tests) |
| Stable `Section::make(...)->id('kebab-case')` | Target anchors for tabs, hash scroll, global search |
| `scrollToSection` / `onNavClick` | Click intercept + smooth scroll (in the Blade Alpine `x-data`) |
| `<x-hash-scroll />` | Smooth scroll for SPA / global-search navigation to `#hash` |
| `open-section` CustomEvent | Hash scroll sets active tab; nav listens and updates `activeId` |

## Adding or renaming a profile section

1. Give the section a stable `->id('kebab-case')` on `Section::make(...)`.
2. Add / update the entry in `EditProfile::sectionNavItems()` (main column only — not the sidebar photo/details stack).
3. Register the section in `AdminDestinationSearch` if it should appear in global search (`url` = profile URL + `#id`).
4. Extend `tests/Feature/ProfileSectionNavTest.php` (and global-search tests when searchable).
5. Keep `scroll-margin-top` on `.fi-profile-page .fi-sc-section[id]` — do not remove it when changing tab height; update `--tido-profile-section-nav-height` instead.

## Smooth scroll vs hash-scroll

| Entry path | Who scrolls |
|------------|-------------|
| Sticky tab click on Profile | Alpine `scrollToSection` in `profile-section-nav` (`preventDefault` + `replaceState`) |
| Global search / external `#hash` / SPA navigate | `<x-hash-scroll />` (`hashchange` / `livewire:navigated`) |

Do **not** rely on bare `<a href="#id">` alone for in-page tabs — the browser jumps instantly and fights smooth scroll. Tab clicks must go through `onNavClick`.

## Checklist for reuse on another long form page

1. Page class for scoped CSS (like `fi-profile-page`).
2. Sticky top marker + `View` of a section-nav Blade (or reuse the profile view with a `sections` list).
3. Stable section `id`s matching the nav list.
4. `scroll-margin-top` that accounts for topbar **and** sticky nav height.
5. Click intercept with `behavior: 'smooth'` + hash `replaceState`.
6. Optional: register sections in `AdminDestinationSearch` + rely on panel `<x-hash-scroll />`.

**Dashboard reuse:** `Dashboard::widgetNavItems()` + `HasDashboardSectionId` on widgets; sticky toolbar CSS in `.tido-dashboard-sticky-toolbar`; scroll-margin on `.tido-dashboard-page .fi-wi-widget[id]`. Tests: [`tests/Feature/DashboardSectionNavTest.php`](../tests/Feature/DashboardSectionNavTest.php).

## Do not

- Put Profile Photo / Personal Details in the sticky tab list (sidebar column only).
- Call Filament SPA navigate on tab hashes (`spa-mode="false"` on tab items).
- Duplicate a second hash-scroll script for Profile — extend `<x-hash-scroll />` for cross-page / SPA entry, keep tab click handling in the nav Alpine.
- Drop `scroll-margin-top` — sections will hide under the sticky tabs.
