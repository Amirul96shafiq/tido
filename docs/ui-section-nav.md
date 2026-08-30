# UI section nav (sticky tabs + smooth scroll)

Shared sticky **in-page tab menu** for long Filament pages. Jumps between anchored sections with smooth scroll, active-tab sync, and hash-aware deep links.

**Not the Home dashboard module switcher.** Finances / Training / Health / Task header icon tabs are documented in [`dashboard-views.md`](dashboard-views.md). On the Finances view, this section-nav pattern is the **widget jump strip** beside the month filter.

**Consumers:** [`EditProfile`](../app/Filament/Pages/Auth/EditProfile.php) at `/admin/profile`, [`Dashboard`](../app/Filament/Pages/Dashboard.php) Finances widget jump tabs beside the month filter, [`ReceiptUploadPage`](../app/Filament/Pages/ReceiptUploadPage.php), Expense/Budget/Recurring Create/Edit resource pages, [`EvolutionApiPage`](../app/Filament/Pages/EvolutionApiPage.php), [`ServiceStatusPage`](../app/Filament/Pages/ServiceStatusPage.php), and Label / Payment Method / Family Member Create/Edit resource pages.

## When to use

Add sticky section tab navigation when a Filament page has **two or more distinct in-page sections** with stable anchor ids (`Section::make(...)->id('kebab-case')` or `id="..."` on Blade `<x-filament::section>`).

**Responsive stacking:** sidebar + main forms, multi-block custom pages, and tables below forms will scroll on small viewports — do **not** wait for a fixed viewport-height threshold. Mobile will exceed any height rule first.

**Opt in via:**

- Custom / content pages: `HasSectionNav` + `sectionNavItems()` + `wrapInSectionNavScope()` (or `content(Schema)`).
- Create/Edit resource pages: `HasStickyBlurFormActions` (composes `HasSectionNav`) + `sectionNavItems()` on the page or form schema.

**Do not use** on list-only pages, single short auth forms, or pages with one undifferentiated block.

## Source of truth

| Layer                              | Path                                                                                                                                                                   |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Shared trait (form + custom pages) | [`HasSectionNav`](../app/Filament/Concerns/HasSectionNav.php) — also composed into [`HasStickyBlurFormActions`](../app/Filament/Concerns/HasStickyBlurFormActions.php) |
| Tab UI + Alpine                    | [`resources/views/filament/schemas/components/section-nav.blade.php`](../resources/views/filament/schemas/components/section-nav.blade.php)                            |
| Shared nav CSS                     | `.tido-section-nav` in [`resources/css/app.css`](../resources/css/app.css)                                                                                             |
| SPA / global-search hash scroll    | [`<x-hash-scroll />`](../resources/views/components/hash-scroll.blade.php) (panel render hook)                                                                         |
| Global search sections             | [`AdminDestinationSearch`](../app/Filament/GlobalSearch/AdminDestinationSearch.php)                                                                                    |

Sticky pin mechanics are shared with [`ui-sticky-blur.md`](ui-sticky-blur.md). This doc covers the **tab menu** behaviour on top of that pin.

## What it does

1. Renders Filament native tabs (`<x-filament::tabs>`) as `#section-id` links.
2. Sticks below the panel topbar via `tido-sticky-marker--top` inside `tido-sticky-scope`.
3. On tab click: `preventDefault` → `scrollIntoView({ behavior: 'smooth' })` → `history.replaceState` for the hash (avoids the browser’s instant jump).
4. Marks the active tab from the section currently crossing the visible sticky-nav boundary while scrolling, and from the URL hash / `open-section` window events.
5. Hides the native horizontal scrollbar on overflow tabs; shows left/right gradient fades when more tabs are off-screen (`updateScrollHints`, `tido-section-nav--can-scroll-left` / `--can-scroll-right`).
6. Resets tab strip horizontal scroll to the leftmost position when the page returns to the top (`resetTabsScrollAtPageTop`); the first section also forces `scrollLeft = 0` in `scrollActiveTabIntoView` (avoids leftover offset from `inline: 'nearest'`).
7. Supports click-and-hold horizontal drag on the tab strip (`onTabPointerDown` / `onTabPointerMove` / `endTabDrag`); mouse pointers use the custom drag path, while touch pointers use native horizontal scrolling and only set the drag guard after the threshold. `setPointerCapture` is applied only after the threshold for non-touch pointers so a plain click still targets the `<a>` tab link. Native `<a>` drag is blocked (`dragstart` `preventDefault`, `draggable="false"`, `-webkit-user-drag: none`) so the browser does not cancel the pointer with `pointercancel`.

## Contract (do not invent a second pattern)

| Token                                                    | Role                                                                                                                                                                                                                                                                                   |
| -------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.tido-section-nav`                                      | Root wrapper + Alpine state                                                                                                                                                                                                                                                            |
| `--tido-section-nav-height`                              | Used in page `scroll-margin-top` so anchors clear the sticky tabs                                                                                                                                                                                                                      |
| `sections` view data                                     | `list<array{label: string, id: string}>` passed to the Blade view                                                                                                                                                                                                                      |
| `ariaLabel` view data                                    | Accessible label for the tab list (page-specific; default `Page sections`)                                                                                                                                                                                                             |
| Stable anchor `id`s                                      | Target anchors for tabs, hash scroll, global search                                                                                                                                                                                                                                    |
| `scrollToSection` / `onNavClick`                         | Click intercept + smooth scroll (in the Blade Alpine `x-data`)                                                                                                                                                                                                                         |
| `<x-hash-scroll />`                                      | Smooth scroll for SPA / global-search navigation to `#hash`; expense line-item search emits one result per match (`Item` + `#expense-item-{id}`), activates Line Items, expands the row, scrolls to its label                                                                          |
| `open-section` CustomEvent                               | Hash scroll sets active tab; nav listens and updates `activeId`                                                                                                                                                                                                                        |
| `.tido-section-nav__frame` / `__fade`                    | Scroll frame + conditional edge gradient cues                                                                                                                                                                                                                                          |
| `updateScrollHints` / `canScrollLeft` / `canScrollRight` | Alpine scroll overflow detection (scroll + `ResizeObserver`)                                                                                                                                                                                                                           |
| `resetTabsScrollAtPageTop`                               | When page scroll returns to the top, reset `.fi-tabs` `scrollLeft` to 0                                                                                                                                                                                                                |
| `syncActiveSection` / `scheduleActiveSectionSync`        | Throttled scroll-position tracking against the visible nav boundary, used for desktop and mobile layouts                                                                                                                                                                               |
| `onTabPointerDown` / `onTabPointerMove` / `endTabDrag`   | Mouse/pointer drag-to-scroll on `.fi-tabs`; touch pointers leave horizontal movement to native scrolling and only use the drag guard; `setPointerCapture` only after `dragThreshold` for non-touch pointers (capturing on pointerdown retargets click to `<nav>` and breaks tab links) |
| `dragMoved` / `dragThreshold`                            | Suppress tab click after a real horizontal drag                                                                                                                                                                                                                                        |
| `tido-section-nav--dragging`                             | Active during the pointer gesture; `cursor: grabbing` + `user-select: none`                                                                                                                                                                                                            |

## Profile

| Layer                   | Path                                                                                       |
| ----------------------- | ------------------------------------------------------------------------------------------ |
| Section list            | `EditProfile::sectionNavItems()`                                                           |
| Sticky pin + form scope | `EditProfile::getFormContentComponent()` — `tido-sticky-scope` + `tido-sticky-marker--top` |
| Page class              | `.fi-profile-page` from `EditProfile::getPageClasses()`                                    |
| Scroll offset CSS       | [`.fi-profile-page .fi-sc-section[id]`](../resources/css/app.css)                          |
| Tests                   | [`tests/Feature/ProfileSectionNavTest.php`](../tests/Feature/ProfileSectionNavTest.php)    |

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

Home module tabs (Finances / Training / Health / Task) are **not** section nav — see [`dashboard-views.md`](dashboard-views.md).

| Layer                    | Path                                                                                                                                                                                            |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list             | `Dashboard::widgetNavItems()` (Finances view only)                                                                                                                                              |
| Finance overview anchors | Per-stat `id`s on `MonthlySpendingOverview` via `Stat::extraAttributes()` (`total-spent`, `spending-forecast`, `sst-tax-paid`, `receipts-processed`)                                            |
| Other widget anchor ids  | `HasDashboardSectionId` on chart/table widgets                                                                                                                                                  |
| Sticky toolbar           | `.tido-dashboard-sticky-toolbar` + `.tido-dashboard-sticky-toolbar-nav`                                                                                                                         |
| Page class               | `.tido-dashboard-page`                                                                                                                                                                          |
| Scroll offset CSS        | [`.tido-dashboard-page .fi-wi-widget[id]`](../resources/css/app.css), [`.tido-dashboard-page .fi-wi-stats-overview-stat[id]`](../resources/css/app.css) + `--tido-dashboard-section-nav-offset` |
| Tests                    | [`tests/Feature/DashboardSectionNavTest.php`](../tests/Feature/DashboardSectionNavTest.php)                                                                                                     |

## Add Receipts

| Layer                      | Path                                                                                                                                                    |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list               | `ReceiptUploadPage::sectionNavItems()`                                                                                                                  |
| Sticky pin + content scope | `ReceiptUploadPage::content()` — `wrapInSectionNavScope()` + content partial                                                                            |
| Page class                 | `.fi-upload-receipts-page`                                                                                                                              |
| Anchors                    | `#add-receipts`, `#recent-uploads` in [`receipt-upload-content.blade.php`](../resources/views/filament/pages/partials/receipt-upload-content.blade.php) |
| Tests                      | [`tests/Feature/ReceiptUploadSectionNavTest.php`](../tests/Feature/ReceiptUploadSectionNavTest.php)                                                     |

## Expense Create / Edit

| Layer        | Path                                                                                                                        |
| ------------ | --------------------------------------------------------------------------------------------------------------------------- |
| Section list | `ExpenseForm::sectionNavItems()` — wired on Create/Edit pages                                                               |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                                    |
| Page class   | `.fi-expense-form-page`                                                                                                     |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`ExpenseForm.php`](../app/Filament/Resources/Expenses/Schemas/ExpenseForm.php) |
| Tests        | [`tests/Feature/ExpenseFormSectionNavTest.php`](../tests/Feature/ExpenseFormSectionNavTest.php)                             |

## Budget Create / Edit

| Layer        | Path                                                                                                                     |
| ------------ | ------------------------------------------------------------------------------------------------------------------------ |
| Section list | `BudgetForm::sectionNavItems()` — Create and Edit include the performance tab                                            |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                                 |
| Page class   | `.fi-budget-form-page`                                                                                                   |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`BudgetForm.php`](../app/Filament/Resources/Budgets/Schemas/BudgetForm.php) |
| Tests        | [`tests/Feature/BudgetFormSectionNavTest.php`](../tests/Feature/BudgetFormSectionNavTest.php)                            |

## Recurring Create / Edit

| Layer        | Path                                                                                                                                                                                                     |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list | `RecurringForm::sectionNavItems()` — Create and Edit include the Recurring Payment Due Preview tab                                                                                                       |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                                                                                                                 |
| Page class   | `.fi-recurring-form-page`                                                                                                                                                                                |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`RecurringForm.php`](../app/Filament/Resources/Recurrings/Schemas/RecurringForm.php)                                                                        |
| Tests        | [`tests/Feature/RecurringFormSectionNavTest.php`](../tests/Feature/RecurringFormSectionNavTest.php), [`tests/Feature/RecurringDuePreviewFormTest.php`](../tests/Feature/RecurringDuePreviewFormTest.php) |

## Evolution API

| Layer                      | Path                                                                                                                                                                                                                             |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list               | `EvolutionApiPage::sectionNavItems()`                                                                                                                                                                                            |
| Sticky pin + content scope | `EvolutionApiPage::content()` — `wrapInSectionNavScope()` + content partial                                                                                                                                                      |
| Page class                 | `.fi-evolution-api-page`                                                                                                                                                                                                         |
| Anchors                    | `#evolution-link-device`, `#evolution-connection`, `#evolution-whatsapp-lid`, `#evolution-connection-history` in [`evolution-api-content.blade.php`](../resources/views/filament/pages/partials/evolution-api-content.blade.php) |
| Tests                      | [`tests/Feature/EvolutionApiSectionNavTest.php`](../tests/Feature/EvolutionApiSectionNavTest.php)                                                                                                                                |

## Service Status

| Layer                      | Path                                                                                                                                                              |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list               | `ServiceStatusPage::sectionNavItems()`                                                                                                                            |
| Sticky pin + content scope | `ServiceStatusPage::content()` — `wrapInSectionNavScope()` + content partial                                                                                      |
| Page class                 | `.fi-service-status-page`                                                                                                                                         |
| Anchors                    | `#service-summary-report`, `#service-status` in [`service-status-content.blade.php`](../resources/views/filament/pages/partials/service-status-content.blade.php) |
| Summary sticky             | `.fi-service-status-summary-sticky` — content-height card sticks below the section tabs on `lg+` while Status scrolls (`resources/css/app.css`)                   |
| Tests                      | [`tests/Feature/ServiceStatusSectionNavTest.php`](../tests/Feature/ServiceStatusSectionNavTest.php)                                                               |

## Label Create / Edit

| Layer        | Path                                                                                                                  |
| ------------ | --------------------------------------------------------------------------------------------------------------------- |
| Section list | `LabelForm::sectionNavItems()` — wired on Create/Edit pages                                                           |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                              |
| Page class   | `.fi-label-form-page`                                                                                                 |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`LabelForm.php`](../app/Filament/Resources/Labels/Schemas/LabelForm.php) |
| Tests        | [`tests/Feature/LabelFormSectionNavTest.php`](../tests/Feature/LabelFormSectionNavTest.php)                           |

## Payment Method Create / Edit

| Layer        | Path                                                                                                                                          |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Section list | `PaymentMethodForm::sectionNavItems()` — wired on Create/Edit pages                                                                           |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                                                      |
| Page class   | `.fi-payment-method-form-page`                                                                                                                |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`PaymentMethodForm.php`](../app/Filament/Resources/PaymentMethods/Schemas/PaymentMethodForm.php) |
| Tests        | [`tests/Feature/PaymentMethodFormSectionNavTest.php`](../tests/Feature/PaymentMethodFormSectionNavTest.php)                                   |

## Family Member Create / Edit

| Layer        | Path                                                                                                                                       |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Section list | `FamilyMemberForm::sectionNavItems()` — wired on Create/Edit pages                                                                         |
| Sticky pin   | `HasStickyBlurFormActions` + `sectionNavItems()` on page                                                                                   |
| Page class   | `.fi-family-member-form-page`                                                                                                              |
| Anchors      | `->id(...)` on each `Section::make(...)` in [`FamilyMemberForm.php`](../app/Filament/Resources/FamilyMembers/Schemas/FamilyMemberForm.php) |
| Tests        | [`tests/Feature/FamilyMemberFormSectionNavTest.php`](../tests/Feature/FamilyMemberFormSectionNavTest.php)                                  |

## Smooth scroll vs hash-scroll

| Entry path                                      | Who scrolls                                                                   |
| ----------------------------------------------- | ----------------------------------------------------------------------------- |
| Sticky tab click                                | Alpine `scrollToSection` in `section-nav` (`preventDefault` + `replaceState`) |
| Global search / external `#hash` / SPA navigate | `<x-hash-scroll />` (`hashchange` / `livewire:navigated`)                     |

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
