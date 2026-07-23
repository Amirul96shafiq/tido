# UI tooltips (Filament Tippy)

Icon-only CTAs must use Filament’s Tippy tooltips (`x-tooltip` / `->tooltip()`), not the browser `title` attribute. Browser titles render as a white box with a black border; Tippy uses the panel theme (see [ui-dark-theme.md](ui-dark-theme.md)).

## Why

Filament’s `<x-filament::icon-button>` / `Action::iconButton()` falls back to `title="{{ $label }}"` when `tooltip` is unset. That looks broken next to the notifications bell and other Tippy tooltips.

## Global table defaults

Configured in [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) via `Table::configureUsing`:

| Trigger | Method | Tooltip source |
|---------|--------|----------------|
| Ungrouped record actions (View / Edit / Delete / …) | `modifyUngroupedRecordActionsUsing` | `->iconButton()->tooltip(fn (Action $action) => $action->getLabel())` |
| Filters toggle | `filtersTriggerAction` | `->tooltip(fn (Action $action) => $action->getLabel())` |
| Column manager | `columnManagerTriggerAction` | `->tooltip(fn (Action $action) => $action->getLabel())` |

Do **not** re-declare `->tooltip()` on every resource table unless the label must differ from the action label.

**Test:** `tests/Feature/FilamentResourceTest.php` → `resource table icon actions use filament tooltips`.

## Filament Actions (PHP)

```php
Action::make('previousMonth')
    ->label('Previous month')
    ->tooltip('Previous month')
    ->iconButton();
```

Prefer `->tooltip()` whenever the trigger is an icon button. Matching Dashboard month controls in [`Dashboard.php`](../app/Filament/Pages/Dashboard.php).

## Blade / Alpine (`x-tooltip`)

Match the notifications bell:

```blade
x-tooltip="{
    content: @js('Close'),
    theme: $store.theme,
}"
```

Keep `aria-label` for accessibility. Do **not** also set `title=` (double tooltips).

## Mobile (below `sm`)

Filament Tippy is **disabled** below Tailwind `sm` (`max-width: 639px`):

| Layer | File |
|-------|------|
| Cancel show + `touch: false` | [`resources/js/disable-mobile-tippy.js`](../resources/js/disable-mobile-tippy.js) |
| Hide any mounted Tippy root | [`resources/css/app.css`](../resources/css/app.css) (`[data-tippy-root]`) |
| Asset registration | [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php) |

**Keep** `aria-label` (or Action labels) so icon CTAs stay accessible when Tippy is off.

**Exception:** Chart.js widget tooltips (tap a chart segment for details) are **not** Tippy — they stay enabled. Theming lives in [`filament-chart-js-plugins.js`](../resources/js/filament-chart-js-plugins.js).

**Exception:** Service Status uptime bar segments use `data-tippy-mobile` — Tippy stays enabled below `sm` with `trigger: 'click'`. See [service-status.md](service-status.md).

**Test:** `tests/Feature/MobileTippyTest.php`.

### High z-index custom modals

Tippy defaults to `zIndex: 9999`. Custom shells that use `z-index: 99999` (changelog, restore backup) must raise Tippy above the shell:

```blade
x-tooltip="{
    content: @js('Close'),
    theme: $store.theme,
    zIndex: 100000,
}"
```

Without this, Tippy mounts and is “visible” in the DOM but renders **behind** the modal.

**Reference:** [`changelog-modal.blade.php`](../resources/views/components/changelog-modal.blade.php), [`restore-backup-modal.blade.php`](../resources/views/components/restore-backup-modal.blade.php).

## Published / custom triggers already covered

| CTA | Location |
|-----|----------|
| Profile menu | `resources/views/vendor/filament-panels/components/user-menu.blade.php` |
| Guest auth menu | `resources/views/components/auth-menu.blade.php` |
| Notifications bell | `resources/views/vendor/filament-panels/components/topbar/database-notifications-trigger.blade.php` |
| Notifications Filter | `resources/views/filament/livewire/database-notifications.blade.php` |
| Filament modal Close | `resources/views/vendor/filament/components/modal/index.blade.php` (`:tooltip` on icon-button) |
| Changelog CTAs | `resources/views/components/changelog-modal.blade.php` |
| Restore backup Close | `resources/views/components/restore-backup-modal.blade.php` |
| Service Status bars | `resources/views/filament/pages/service-status.blade.php` (`data-tippy-mobile`) |

When publishing more Filament views for tooltip-only tweaks, keep the override set **minimal** (only the files that differ from vendor).

## Checklist for new icon CTAs

1. Prefer Filament `Action` + `->tooltip()` / `->iconButton()`, or `<x-filament::icon-button tooltip="…">`.
2. Custom Blade: use `x-tooltip` + `theme: $store.theme` — never bare `title=` for user-facing CTAs.
3. If the parent layer is `z-index` ≥ 9999, set Tippy `zIndex` higher than that layer.
4. Keep `aria-label` (or Action label) for screen readers.
5. Cover PHP Action tooltips with Pest (`getTooltip()` equals label) when changing `AppServiceProvider` table defaults.

## Related

- Dark Tippy colors: [ui-dark-theme.md](ui-dark-theme.md)
- Modal blur / width: [ui-modal-overlay.md](ui-modal-overlay.md)
- Agent Filament notes: [agent-onboarding.md](agent-onboarding.md) § Filament UI
