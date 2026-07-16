# UI empty states

Canonical pattern for illustrated “nothing here” panels in tido.

## Reference implementation

**Source of truth (standalone page):** [`resources/views/errors/email-change-expired.blade.php`](../resources/views/errors/email-change-expired.blade.php)

That page established the visual language:

1. Circular icon well with soft tinted background  
2. Pulsing ring around the icon  
3. Bold heading  
4. Short supporting description  
5. Full-width primary CTA  

**Reusable Filament/panel component:** [`resources/views/components/empty-state-panel.blade.php`](../resources/views/components/empty-state-panel.blade.php)

Styles live under `.fi-no-empty-panel*` in [`resources/css/app.css`](../resources/css/app.css) (light + dark).

## When to use

Use `<x-empty-state-panel>` for in-app empty results (filtered lists, search misses, empty drawers) where a plain sentence is too weak.

Do **not** replace Filament’s built-in database-notifications empty modal (no notifications at all) unless product asks — that still uses Filament’s native empty modal API.

## Usage

```blade
<x-empty-state-panel
    heading="No matches found"
    description="No notifications match your current search or filters. Try adjusting your criteria, or clear them to see everything again."
    icon="heroicon-o-magnifying-glass"
    icon-color="primary"
>
    <x-slot name="actions">
        <x-filament::button
            color="primary"
            wire:click="clearSearchAndFilters"
            type="button"
        >
            Clear search &amp; filters
        </x-filament::button>
    </x-slot>
</x-empty-state-panel>
```

### Props

| Prop | Required | Notes |
|------|----------|--------|
| `heading` | yes | Short title |
| `description` | yes | One or two sentences |
| `icon` | no | Heroicon name / enum-compatible string (default magnifying glass) |
| `icon-color` | no | `primary` (default), `danger`, `warning`, `success`, `gray` |
| `actions` slot | no | Prefer a single full-width `x-filament::button` |

## Current consumers

| Surface | File |
|---------|------|
| Database notifications — filtered/search empty | `resources/views/filament/livewire/database-notifications.blade.php` |
| Email change link expired | `resources/views/errors/email-change-expired.blade.php` (standalone HTML; keep in sync visually) |
| Dashboard chart widgets (empty month) | `resources/views/filament/widgets/chart-with-empty-state.blade.php` + `HasChartEmptyState` — Spending by Label, Top Merchants, Payment Method, Receipts by Source (CTA: Upload Receipts) |

## Agent checklist

1. Prefer `<x-empty-state-panel>` over ad-hoc centered text  
2. Match copy tone: calm, specific, one clear next action — see [ui-copy-style.md](ui-copy-style.md)  
3. After CSS changes to `.fi-no-empty-panel*`, run `npm run build` or `npm run dev`  
4. Cover the empty path in a Pest feature test (`assertSee` heading + CTA label)
