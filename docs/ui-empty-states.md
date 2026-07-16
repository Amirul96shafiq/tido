# UI empty states

Canonical pattern for illustrated “nothing here” panels in tido.

## Reference implementation

**Source of truth (standalone page):** [`resources/views/errors/email-change-expired.blade.php`](../resources/views/errors/email-change-expired.blade.php)

That page established the visual language:

1. Circular icon well with soft tinted background  
2. Pulsing ring around the icon (custom panels; tables/charts use a **static** well)  
3. Bold heading  
4. Short supporting description  
5. Primary CTA when the next step is not already on the page  

**Reusable Filament/panel component:** [`resources/views/components/empty-state-panel.blade.php`](../resources/views/components/empty-state-panel.blade.php)

Styles live under `.fi-no-empty-panel*` in [`resources/css/app.css`](../resources/css/app.css) (light + dark). Table empties use `.fi-ta-empty-state*` (same visual contract, static icon).

## When to use which pattern

| Surface | Pattern |
|---------|---------|
| Filament **tables** (resources, table widgets, page tables) | `emptyStateHeading` / `Description` / `Icon` / optional `Actions` + shared `.fi-ta-empty-state*` CSS |
| Custom Blade / filtered drawers / search misses | `<x-empty-state-panel>` |
| Dashboard **charts** | `HasChartEmptyState` + `chart-with-empty-state` view |
| Database notifications (no notifications at all) | Filament native empty modal — do not replace unless product asks |

## Filament tables (required for every new table)

Always configure empty state on new `Table::configure` / `table()` definitions. Never ship Filament’s default “No [model]” alone.

### Required API

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

return $table
    ->emptyStateHeading('No invoices yet')
    ->emptyStateDescription('Upload a receipt or add an invoice to start tracking spending.')
    ->emptyStateIcon('heroicon-o-document-text')
    ->emptyStateActions([
        Action::make('uploadReceipts')
            ->label('Upload Receipts')
            ->icon(Heroicon::Plus)
            ->url(ReceiptUploadPage::getUrl())
            ->button(),
    ]);
```

| Method | Required | Notes |
|--------|----------|-------|
| `emptyStateHeading()` | yes | Short (*No X yet* / *No X*) |
| `emptyStateDescription()` | yes | One impersonal sentence — see [ui-copy-style.md](ui-copy-style.md) |
| `emptyStateIcon()` | yes | Heroicon outline matching the resource |
| `emptyStateActions()` | optional | Single primary `Action::make()->icon(Heroicon::Plus)->button()` |

### CTA rules

- **Include** a CTA when the next step is elsewhere (create URL, upload page, etc.).
- **Omit** when create/upload/connect already lives on the same page above the table (e.g. Upload Receipts form, WhatsApp Link device).
- Prefer one primary button with a **plus prefix icon** (`Heroicon::Plus` / `heroicon-m-plus`); content-sized (`w-auto` via shared CSS), not full-card width.
- Do **not** use `emptyState(view(...))` unless native actions cannot express the CTA.

### Visual contract

Rely on shared `.fi-ta-empty-state*` styles in `resources/css/app.css`:

- Static circular icon well (no pulse)
- Bold heading + short description
- Content-sized primary CTA

Do not invent a second empty layout for tables.

### Checklist for new tables

1. Set heading, description, and icon  
2. Decide CTA: add `emptyStateActions` with `->icon(Heroicon::Plus)` or omit (control already on page)  
3. Copy: impersonal — no *we* / *you* / *your*  
4. Pest: `assertSee` heading; assert CTA label when present  
5. After CSS changes to `.fi-ta-empty-state*`, run `npm run build` or `npm run dev`

## Custom panel usage (`<x-empty-state-panel>`)

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
|------|----------|-------|
| `heading` | yes | Short title |
| `description` | yes | One or two sentences |
| `icon` | no | Heroicon name / enum-compatible string (default magnifying glass) |
| `icon-color` | no | `primary` (default), `danger`, `warning`, `success`, `gray` |
| `actions` slot | no | Prefer a single `x-filament::button` |

## Current consumers

| Surface | File |
|---------|------|
| Database notifications — filtered/search empty | `resources/views/filament/livewire/database-notifications.blade.php` |
| Email change link expired | `resources/views/errors/email-change-expired.blade.php` (standalone HTML; keep in sync visually) |
| Dashboard chart widgets (empty month) | `resources/views/filament/widgets/chart-with-empty-state.blade.php` + `HasChartEmptyState` |
| Invoices list | `app/Filament/Resources/Invoices/Tables/InvoicesTable.php` |
| Budgets list | `app/Filament/Resources/Budgets/Tables/BudgetsTable.php` |
| Labels list | `app/Filament/Resources/Labels/Tables/LabelsTable.php` |
| Backups list | `app/Filament/Resources/Backups/Tables/BackupsTable.php` |
| Recent Receipts (dashboard) | `app/Filament/Widgets/RecentReceipts.php` |
| Upload Receipts page table | `app/Filament/Pages/ReceiptUploadPage.php` |
| WhatsApp connection history | `app/Filament/Pages/WhatsAppConnectionPage.php` |

## Agent checklist

1. **New Filament table:** follow the Filament tables section above (heading + description + icon + CTA decision)  
2. **Custom Blade empty:** prefer `<x-empty-state-panel>` over ad-hoc centered text  
3. Match copy tone: calm, specific — see [ui-copy-style.md](ui-copy-style.md)  
4. After CSS changes to `.fi-no-empty-panel*` or `.fi-ta-empty-state*`, run `npm run build` or `npm run dev`  
5. Cover the empty path in a Pest feature test (`assertSee` heading + CTA label when present)
