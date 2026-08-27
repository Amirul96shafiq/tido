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
| User menu version footer | `slate-400` text with `slate-700` divider | See `.fi-user-menu-version-footer` in `app.css` |
| UI tooltips (Tippy default / dark) | `slate-700` | Lighter than chrome so they don’t disappear |
| Chart tooltips (dark) | `slate-700` via `--color-slate-700` | Fallback hex `#334155` |
| Custom scrollbar thumb (dark) | slate-700 @ 50% → hover slate-600 @ 70% | `.custom-scrollbar`, `.fi-dropdown-panel`, Filament slide-overs (`.fi-modal-slide-over … > .fi-modal-window`), centered action modals (`.fi-modal:not(.fi-modal-slide-over) > .fi-modal-window-ctn`), database notification `.fi-modal-content`. Page scroller + `.fi-sidebar-nav` use the same webkit overlay in Chromium (do **not** set `scrollbar-width` / `scrollbar-color` on those — it restores the native OS bar) |

Light mode is unchanged: white / gray surfaces; Tippy `light` theme stays white.

## Borders & elevation

Surface separation uses **1px borders**, not drop shadows. Apply the same tokens on panel chrome and nested panels/menus so light and dark stay consistent.

| Mode | Border token | Notes |
|------|--------------|--------|
| Light | `var(--color-gray-100)` (or Filament `border-gray-100` / equivalent) | Restrained outline on white / gray surfaces |
| Dark | `color-mix(in srgb, var(--color-slate-700) 60%, transparent)` | Same mix as sidebar/topbar/account-switcher borders |
| Form fields (dark) | `ring-white/10` on solid `gray-900` | Documented above — ring stands in for the border look on inputs |

**Do not** add elevation `box-shadow` / Tailwind `shadow-*` on chrome, nested panels, or menus to fake a border. Prefer `box-shadow: none` when overriding Filament defaults that add soft elevation.

Reference implementations in `resources/css/app.css`:

- `.fi-sidebar` / `.fi-topbar` — 1px border + `box-shadow: none`
- `.fi-account-switcher-section` / `.fi-account-switcher-expanded` — same light/dark border tokens, no elevation shadow

**Allowed exceptions:** focus rings (`focus:ring-*`) and intentional modal elevation that already exists. Do not extend those shadows to nested user-menu panels or new chrome.

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
4. **Tooltips** — Do not use Tippy’s default `#333` or Chart.js `#333333`. Tippy is overridden in `app.css`; charts read `--color-slate-700` in `filament-chart-js-plugins.js`. Icon CTAs must use Filament Tippy (`->tooltip()` / `x-tooltip`), not browser `title` — see [ui-tooltips.md](ui-tooltips.md). Custom shells at `z-index: 99999` need Tippy `zIndex: 100000` or tooltips render behind the shell. Native `<x-filament::modal>` does not.
5. **Scrollable panels** — Add `custom-scrollbar` on custom `overflow-y-auto` regions. Filament slide-overs (View details, changelog, resource View) are themed via `.fi-modal-slide-over … > .fi-modal-window` in `app.css`. Centered action modals (Ollama Edit setup / Configure) scroll on `.fi-modal-window-ctn` and are themed via `.fi-modal:not(.fi-modal-slide-over) > .fi-modal-window-ctn`. Database notifications pin header/footer and scroll `.fi-modal-content` (also themed there). Do not put `custom-scrollbar` on an inner non-scrolling list. Filament `.fi-dropdown-panel` scrollbars are already themed. Sidebar nav (`.fi-sidebar-nav`) matches the page scroller: Chromium `::-webkit-scrollbar` only; Firefox `scrollbar-width` / `scrollbar-color` inside `@supports not selector(::-webkit-scrollbar)`. Do not re-add those standard props on `.fi-sidebar-nav` in Chromium.
6. **Hardcoded utilities** — Prefer `slate-*` (or Filament `gray-*`) over `zinc-*` for new dark-mode classes in Blade/CSS.
7. **Solid gold CTAs** — Rely on `ButtonComponent` for Filament buttons; do not reintroduce white label text on primary fills in dark mode.
8. **Borders & elevation** — Separate surfaces with the 1px light/dark border tokens above. Do not use drop shadows as a “shadow border” on chrome or nested panels (see **Borders & elevation**).

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
