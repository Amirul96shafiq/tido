# Dashboard views (modular hub)

Top-level **dashboard modules** on the Filament Home page. These are distinct from Finances **in-page section tabs** (widget jump links) documented in [`ui-section-nav.md`](ui-section-nav.md).

## Product modules

| View | Status | Tab icon | Query |
|------|--------|----------|-------|
| **Finance** | Shipped — MYR receipts, budgets, month analytics | `heroicon-m-calculator` | default (`?view=` omitted) |
| **Training** | Coming soon | `heroicon-m-bolt` | `?view=training` |
| **Health** | Coming soon | `heroicon-m-heart` | `?view=health` |
| **Task** | Coming soon | `heroicon-m-rectangle-stack` | `?view=task` |

Sidebar nav group **Finances** (Upload Receipts, Invoices, Budgets) is the CRUD surface for the Finance module. It is not the same as the dashboard view tab (**Finance**).

## Source of truth

| Layer | Path |
|-------|------|
| View constants, tabs, content switch | [`app/Filament/Pages/Dashboard.php`](../app/Filament/Pages/Dashboard.php) — `VIEW_*`, `VIEWS`, `viewTabs()`, `setDashboardView()`, `comingSoonDashboardContent()`, `content()` |
| URL sync | Livewire `#[Url(as: 'view', except: 'finances', history: true)]` on `$dashboardView` |
| Header icon tabs | [`resources/views/filament/pages/partials/dashboard-view-tabs.blade.php`](../resources/views/filament/pages/partials/dashboard-view-tabs.blade.php) |
| Hook placement | `PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE` scoped to `Dashboard` in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php) |
| Coming-soon shell | [`resources/views/filament/pages/partials/coming-soon-dashboard-content.blade.php`](../resources/views/filament/pages/partials/coming-soon-dashboard-content.blade.php) |
| Tab CSS | `.tido-dashboard-view-tabs` in [`resources/css/app.css`](../resources/css/app.css) |

## Behaviour

1. Header tabs are **icon-only** with a leading **Focus:** label, Filament Tippy tooltips + `aria-label` (see [`ui-tooltips.md`](ui-tooltips.md)).
2. While `setDashboardView('…')` is in flight, that tab’s icon swaps to `<x-filament::loading-indicator>` (`wire:loading` / `wire:target`).
3. **Finance** renders month filter + widget section nav + widgets (sticky toolbar — [`ui-sticky-blur.md`](ui-sticky-blur.md), [`ui-section-nav.md`](ui-section-nav.md)).
4. Non-finances views that return meta from `comingSoonDashboardContent()` render the shared coming-soon partial (no finance widgets).
5. Invalid `?view=` values fall back to Finance in `booted()`.

## Adding a dashboard module

1. Add `VIEW_*` constant and append to `Dashboard::VIEWS`.
2. Add a row to `Dashboard::viewTabs()` (`view`, `label`, `icon`).
3. Either:
   - **Placeholder:** extend `comingSoonDashboardContent()` with `id`, `heading`, `icon`, `description`, or
   - **Shipped UI:** branch in `content()` (or drop out of coming-soon) with real schema/widgets.
4. Update Pest coverage in [`tests/Feature/TrainingDashboardTest.php`](../tests/Feature/TrainingDashboardTest.php) (coming-soon switch + query string) and header tab assertions in [`tests/Feature/DashboardGreetingTest.php`](../tests/Feature/DashboardGreetingTest.php).
5. Do not invent Training / Health / Task domain models or sidebar nav groups until those modules are designed.

## Tests

| File | Covers |
|------|--------|
| [`tests/Feature/TrainingDashboardTest.php`](../tests/Feature/TrainingDashboardTest.php) | Switch to Training / Health / Task, query-string deep links, invalid view ignored |
| [`tests/Feature/DashboardGreetingTest.php`](../tests/Feature/DashboardGreetingTest.php) | Header tabs present (labels, loading targets) |
| [`tests/Feature/DashboardSectionNavTest.php`](../tests/Feature/DashboardSectionNavTest.php) | Finances **widget** section nav only (not view tabs) |
