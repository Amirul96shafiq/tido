# UI modal overlay blur

Canonical pattern for blurred modal backdrops in tido (matches the changelog modal).

## Reference implementation

**Changelog (custom Alpine modal):** [`resources/views/components/changelog-modal.blade.php`](../resources/views/components/changelog-modal.blade.php)

```blade
<div class="absolute inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-md transition-opacity"
     @click="show = false"
     aria-hidden="true"></div>
```

**Shared CSS hook:** [`resources/css/app.css`](../resources/css/app.css) — class `.fi-modal-overlay-blur` on Filament’s `.fi-modal-close-overlay`.

## Filament action modals (header actions, table actions)

Use `modalWidth()` for a compact dialog and `extraModalOverlayAttributes()` for blur:

```php
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

Action::make('pairWithCode')
    ->modalWidth(Width::Small)
    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true)
    ->form([
        // ...
    ]);
```

`merge: true` keeps Filament’s default overlay classes (`fi-modal-close-overlay`) and appends the blur hook.

**Example in app:** `pairWithCode` on [`EvolutionApiPage`](../app/Filament/Pages/EvolutionApiPage.php).

### Modal width scale

Filament `Width` enum maps to Tailwind max-width (`ExtraSmall` … `SevenExtraLarge`). Prefer:

| Use case | Width |
|----------|-------|
| Single field / confirm | `Width::Small` |
| Short form (2–4 fields) | `Width::Medium` |
| Default Filament action modal | (omit — Filament default) |
| Wide content | `Width::Large` or larger |

## Filament Blade modals (`<x-filament::modal>`)

Add a hook class on the modal root and target the overlay in `app.css`:

```blade
<x-filament::modal
    class="fi-evolution-api-details"
    slide-over
    ...
>
```

```css
.fi-modal.fi-evolution-api-details > .fi-modal-close-overlay {
    @apply backdrop-blur-md;
}
```

**Example in app:** EvolutionAPI connection details slide-over in [`evolution-api.blade.php`](../resources/views/filament/pages/evolution-api.blade.php).

## Database notifications slide-over

Uses the same blur via a panel hook — no PHP change needed:

```css
.fi-no-database > .fi-modal-close-overlay {
    @apply backdrop-blur-md;
}
```

(Component: [`DatabaseNotifications`](../app/Filament/Livewire/DatabaseNotifications.php).)

## Custom Alpine / Blade modals

Inline on the backdrop element (same tokens as changelog):

```html
class="absolute inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-md transition-opacity"
```

## Checklist for new modals

1. Choose **Filament action** vs **`<x-filament::modal>`** vs **custom Alpine**.
2. Apply blur using one of the patterns above — do not ship a dim-only overlay when other modals in the panel use blur.
3. For action modals with one or two fields, set `modalWidth(Width::Small)` (or `Medium`) so the dialog is not full-page wide.
4. Icon CTAs inside the modal must use Filament Tippy (`x-tooltip` / `:tooltip`) — see [ui-tooltips.md](ui-tooltips.md). If the shell uses `z-index` ≥ 9999 (e.g. changelog / restore backup at `99999`), set Tippy `zIndex` higher (e.g. `100000`).
5. After CSS changes, run `npm run build` or `npm run dev` so Filament panel picks up `app.css`.
