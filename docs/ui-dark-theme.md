# Dark theme colors (Slate)

tido’s Filament admin dark mode uses a **Slate** palette with **slate-800** as the main surface. Prefer these tokens over Zinc / neutral `#333` tooltips / default OS scrollbars.

## Source of truth

| Layer | File |
|-------|------|
| Filament gray palette | `app/Providers/Filament/AdminPanelProvider.php` → `->colors(['gray' => …])` |
| Solid CTA button color map | `app/View/Components/ButtonComponent.php` (bound in `AppServiceProvider`) |
| Panel chrome + Tippy + scrollbars | `resources/css/app.css` |
| Chart.js tooltips | `resources/js/filament-chart-js-plugins.js` |

Brand accents stay as configured (`primary` / `success` / `info` golds, `danger` Red, `warning` Amber). Only the **gray / dark surface** system is Slate.

## Filament gray palette

```php
'gray' => array_replace(Color::Slate, [
    900 => Color::Slate[800],
    950 => Color::Slate[800],
]),
```

- Base palette: `Color::Slate`
- Shades **900** and **950** are remapped to **Slate 800** so Filament widgets, sections, tables, and `dark:bg-gray-900` / `dark:bg-gray-950` match the lighter chrome (not near-black navy)

### Do not use `...Color::Slate` spread

PHP’s array spread **reindexes integer keys** (`50` → `0`, etc.). Filament then fails with `Undefined array key 50` (e.g. in `LinkComponent`). Always use `array_replace(Color::Slate, […])`.

## Token map (dark mode)

| Role | Token | Notes |
|------|-------|--------|
| Page / sidebar / topbar / body chrome | `slate-800` | Forced in `app.css` on `.fi-body`, `.fi-sidebar`, `.fi-topbar` |
| Cards, widgets, tables, sections | Filament `gray-900` / `gray-950` | Same visual as slate-800 via palette remap |
| Form fields + repeater/builder items | `gray-900` + `ring-white/10` | Same solid surface + border as sections/widgets (not Filament’s `white/5` / `white/20`) |
| Borders / dividers on chrome | `slate-700` (often ~60% opacity) | Visible against slate-800 |
| Nav / icon hovers & active fills | `slate-700` | e.g. `dark:hover:bg-slate-700/60` |
| Sidebar version badge (expanded) | `slate-700/60` → hover `slate-700` | Border `slate-600/50`; see `AdminPanelProvider` footer hook |
| UI tooltips (Tippy default / dark) | `slate-700` | Lighter than chrome so they don’t disappear |
| Chart tooltips (dark) | `slate-700` via `--color-slate-700` | Fallback hex `#334155` |
| Custom scrollbar thumb (dark) | slate-700 @ 50% → hover slate-600 @ 70% | `.custom-scrollbar` and `.fi-dropdown-panel` |

Light mode is unchanged: white / gray surfaces; Tippy `light` theme stays white.

## Solid CTA buttons (primary gold)

Pale brand golds (`primary` `#FFD07D`, and similarly `success` / `info`) make Filament’s default solid-button map pick **white** text on `dark:bg` shade `600`. That fails WCAG AA and looks washed out on CTAs (Sign in, New budget, Upload, etc.).

Light mode already resolves correctly: pale `bg` (`400`) + **dark primary** text (`950`).

tido overrides Filament’s button color map so that when light mode chose dark text (`text >= 800`) but dark mode fell back to white (`dark:text === 0`), dark mode **mirrors** the light pairing:

| Slot | Value |
|------|--------|
| `dark:bg` / `dark:hover:bg` | Same as light `bg` / `hover:bg` (typically `400` / `300`) |
| `dark:text` / `dark:hover:text` | Same as light `text` / `hover:text` (typically `950` / `800`) |

- Implementation: `App\View\Components\ButtonComponent` extends Filament’s `ButtonComponent`
- Binding: `AppServiceProvider::register()` → `Filament\Support\View\Components\ButtonComponent` → app class
- Covered by `tests/Unit/ButtonComponentColorMapTest.php`
- Do **not** fix this with a CSS `color` override on `.fi-btn` — Filament drives label/icon color via `--dark-text` from the map
- `danger` / vibrant colors stay white-on-color (their light `text` is already `0`)

Custom solid buttons outside Filament should use `text-primary-950` (or `900`) on gold fills, matching changelog modal arrow buttons.

## Practical rules for new UI

1. **Surfaces** — Prefer Filament `bg-white dark:bg-gray-900` (or section/table widgets). Remapped gray already lands on slate-800. Avoid hardcoding `dark:bg-zinc-*` or `dark:bg-gray-950` as a “darker card” unless you intentionally want contrast.
2. **Form fields / repeaters** — Do not reintroduce `dark:bg-white/5` or `dark:ring-white/20` on inputs or repeater/builder items. Dark mode uses solid `bg-gray-900` + `ring-white/10` (see `.fi-input-wrp` / FilePond / `.fi-fo-repeater-item` overrides in `app.css`) to match sections/widgets.
3. **Chrome overrides** — If you force sidebar/topbar/body colors, use `var(--color-slate-800)` and borders with `var(--color-slate-700)`, matching existing blocks in `app.css`.
4. **Tooltips** — Do not use Tippy’s default `#333` or Chart.js `#333333`. Tippy is overridden in `app.css`; charts read `--color-slate-700` in `filament-chart-js-plugins.js`. Icon CTAs must use Filament Tippy (`->tooltip()` / `x-tooltip`), not browser `title` — see [ui-tooltips.md](ui-tooltips.md). Custom modals at `z-index: 99999` need Tippy `zIndex: 100000` or tooltips render behind the shell.
5. **Scrollable panels** — Add `custom-scrollbar` on custom `overflow-y-auto` regions (e.g. changelogs modal). Filament `.fi-dropdown-panel` scrollbars are already themed in `app.css`.
6. **Hardcoded utilities** — Prefer `slate-*` (or Filament `gray-*`) over `zinc-*` for new dark-mode classes in Blade/CSS.
7. **Solid gold CTAs** — Rely on `ButtonComponent` for Filament buttons; do not reintroduce white label text on primary fills in dark mode.

## Hex / RGB cheatsheet

| Token | Approx hex | RGB |
|-------|------------|-----|
| slate-800 | `#1e293b` | `30, 41, 59` |
| slate-700 | `#334155` | `51, 65, 85` |
| slate-600 | `#475569` | `71, 85, 105` |

## Related

- Empty panels: [ui-empty-states.md](ui-empty-states.md)
- Icon CTA tooltips: [ui-tooltips.md](ui-tooltips.md)
- Modal blur: [ui-modal-overlay.md](ui-modal-overlay.md)
- Agent UI notes: [agent-onboarding.md](agent-onboarding.md) § Filament UI
- UI copy voice: [ui-copy-style.md](ui-copy-style.md)
