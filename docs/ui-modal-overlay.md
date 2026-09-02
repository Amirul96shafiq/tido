# UI modal overlay blur

Canonical pattern for blurred modal backdrops in tido.

## Reference implementation

**Filament slide-over panels** (database notifications, changelog): use `<x-filament::modal slide-over>` — blur comes from `.fi-modal-close-overlay` in [`resources/css/app.css`](../resources/css/app.css).

**Changelog slide-over:** [`resources/views/components/changelog-modal.blade.php`](../resources/views/components/changelog-modal.blade.php)

```blade
<x-filament::modal
    id="changelog"
    slide-over
    sticky-header
    sticky-footer
    teleport="body"
    width="md"
    close-button
    class="fi-changelog"
>
```

Opened via `$dispatch('open-modal', { id: 'changelog' })` (user menu, dashboard header, guest auth menu).

**Shared CSS hook:** class `.fi-modal-overlay-blur` on Filament’s `.fi-modal-close-overlay` for action modals.

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

| Use case                      | Width                     |
| ----------------------------- | ------------------------- |
| Single field / confirm        | `Width::Small`            |
| Short form (2–4 fields)       | `Width::Medium`           |
| Default Filament action modal | (omit — Filament default) |
| Wide content                  | `Width::Large` or larger  |

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

**Example in app:** Evolution API connection details slide-over in [`evolution-api-details.blade.php`](../resources/views/filament/pages/partials/evolution-api-details.blade.php).

## Database notifications slide-over

Uses the same blur via a panel hook — no PHP change needed:

```css
.fi-no-database > .fi-modal-close-overlay {
    @apply backdrop-blur-md;
}
```

(Component: [`DatabaseNotifications`](../app/Filament/Livewire/DatabaseNotifications.php).)

## Guest restore modal

Uses `<x-filament::modal>` like changelog (`resources/views/components/restore-backup-modal.blade.php`). Overlay blur comes from `.fi-modal-close-overlay` in `app.css`. Open with `$dispatch('open-modal', { id: 'restore-backup' })`.

## Custom Alpine / Blade modals

Inline on the backdrop element (same tokens as the shared overlay):

```html
class="absolute inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-md
transition-opacity"
```

## Mobile chrome overlays (not this pattern)

Below `lg`, the **sidebar**, **Add sheet**, **user menu**, and **global search modal** use the Filament sidebar close overlay recipe: dim only (`bg-gray-950/50` / `dark:bg-gray-950/75`), no `backdrop-blur`. See [ui-mobile-nav.md](ui-mobile-nav.md). Do not add frost to `.fi-sidebar-close-overlay` or `.tido-chrome-overlay`.

## Checklist for new modals

1. Choose **Filament action** vs **`<x-filament::modal>`** vs **custom Alpine**.
2. Apply blur using one of the patterns above — do not ship a dim-only overlay when other **Filament action / slide-over** modals in the panel use blur. Mobile chrome overlays (sidebar, Add, user menu, global search below `lg`) stay dim-only — see [ui-mobile-nav.md](ui-mobile-nav.md).
3. For action modals with one or two fields, set `modalWidth(Width::Small)` (or `Medium`) so the dialog is not full-page wide.
4. Icon CTAs inside the modal must use Filament Tippy (`x-tooltip` / `:tooltip`) — see [ui-tooltips.md](ui-tooltips.md). Custom shells at `z-index: 99999` need Tippy `zIndex: 100000`. Native `<x-filament::modal>` does not.
5. After CSS changes, run `npm run build` or `npm run dev` so Filament panel picks up `app.css`.
