# Dark theme colors (Slate)

tido’s Filament admin dark mode uses a **Slate** palette with **slate-800** as the main surface. Prefer these tokens over Zinc / neutral `#333` tooltips / default OS scrollbars.

## Source of truth

| Layer | File |
|-------|------|
| Filament gray palette | `app/Providers/Filament/AdminPanelProvider.php` → `->colors(['gray' => …])` |
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
| Borders / dividers on chrome | `slate-700` (often ~60% opacity) | Visible against slate-800 |
| Nav / icon hovers & active fills | `slate-700` | e.g. `dark:hover:bg-slate-700/60` |
| Sidebar version badge (expanded) | `slate-700/60` → hover `slate-700` | Border `slate-600/50`; see `AdminPanelProvider` footer hook |
| UI tooltips (Tippy default / dark) | `slate-700` | Lighter than chrome so they don’t disappear |
| Chart tooltips (dark) | `slate-700` via `--color-slate-700` | Fallback hex `#334155` |
| Custom scrollbar thumb (dark) | slate-700 @ 50% → hover slate-600 @ 70% | `.custom-scrollbar` and `.fi-dropdown-panel` |

Light mode is unchanged: white / gray surfaces; Tippy `light` theme stays white.

## Practical rules for new UI

1. **Surfaces** — Prefer Filament `bg-white dark:bg-gray-900` (or section/table widgets). Remapped gray already lands on slate-800. Avoid hardcoding `dark:bg-zinc-*` or `dark:bg-gray-950` as a “darker card” unless you intentionally want contrast.
2. **Chrome overrides** — If you force sidebar/topbar/body colors, use `var(--color-slate-800)` and borders with `var(--color-slate-700)`, matching existing blocks in `app.css`.
3. **Tooltips** — Do not use Tippy’s default `#333` or Chart.js `#333333`. Tippy is overridden in `app.css`; charts read `--color-slate-700` in `filament-chart-js-plugins.js`.
4. **Scrollable panels** — Add `custom-scrollbar` on custom `overflow-y-auto` regions (e.g. changelogs modal). Filament `.fi-dropdown-panel` scrollbars are already themed in `app.css`.
5. **Hardcoded utilities** — Prefer `slate-*` (or Filament `gray-*`) over `zinc-*` for new dark-mode classes in Blade/CSS.

## Hex / RGB cheatsheet

| Token | Approx hex | RGB |
|-------|------------|-----|
| slate-800 | `#1e293b` | `30, 41, 59` |
| slate-700 | `#334155` | `51, 65, 85` |
| slate-600 | `#475569` | `71, 85, 105` |

## Related

- Empty panels: [ui-empty-states.md](ui-empty-states.md)
- Agent UI notes: [agent-onboarding.md](agent-onboarding.md) § Filament UI
