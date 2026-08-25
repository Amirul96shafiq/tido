# Reduce Motion Preference

Profile **Reduce Motion** disables decorative animation across the admin panel. It complements the OS `prefers-reduced-motion` setting — either one can stop motion.

## Profile control

- Field: `users.reduce_motion` (boolean, default `false`)
- UI: native `Toggle::make('reduce_motion')` in **Personalize & Appearance → PREFERENCES** on [`EditProfile.php`](../app/Filament/Pages/Auth/EditProfile.php)
- Helper: [`App\Support\ReduceMotion`](../app/Support/ReduceMotion.php)

When enabled, count-up, marquee, status pulses, notification badge pings, recurring dues title pings, sidebar chrome transitions, notification timer bars, empty-panel entrance, and smooth scroll helpers use static or instant behavior.

## How it applies

| Layer          | Mechanism                                                                                                                                                                                            |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Server         | [`ReduceMotion::enabled()`](../app/Support/ReduceMotion.php) in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php) `HEAD_START` hook                                           |
| HTML class     | `html.tido-reduce-motion` on `document.documentElement`                                                                                                                                              |
| CSS            | Mirror every `@media (prefers-reduced-motion: reduce)` rule with `html.tido-reduce-motion …` selectors in [`app.css`](../resources/css/app.css)                                                      |
| Count-up       | `window.matchMedia('(prefers-reduced-motion: reduce)')` is wrapped so `.matches` is true when the html class is present (vendor package checks media only)                                           |
| Marquee        | [`x-tido.text-marquee`](../resources/views/components/tido/text-marquee.blade.php) and [`select-value-marquee.js`](../resources/js/select-value-marquee.js) call `window.tidoPrefersReducedMotion()` |
| Smooth scroll  | Section nav, hash scroll, go-to-top/bottom use `behavior: 'auto'` when reduced                                                                                                                       |
| SPA navigation | `sessionStorage` + `livewire:navigating` / `livewire:navigated` restore `html.tido-reduce-motion` and re-snap count-up / marquee via `syncMarqueeMotion()`                                           |

### Client API

```js
window.tidoPrefersReducedMotion(); // OS OR profile preference
window.tidoSetReduceMotion(true); // live toggle on Profile (before save)
```

The `HEAD_START` script must load before Alpine and count-up scripts.

## Status pulses

Connected-state ring pulses use `.tido-status-pulse` in [`app.css`](../resources/css/app.css) instead of inline `animation:` styles. Surfaces: Evolution API, Ollama, Service Status.

## Ping pulses

Unread notification count badges (topbar avatar + menu item) and the Recurring Payment Dues title indicator use `.tido-ping-pulse` instead of Tailwind `animate-ping`. The static inner dot remains visible when motion is reduced; only the expanding ring is hidden.

## Sidebar chrome

When motion is reduced, sidebar collapse/expand skips width morph, clip-path, FIN/SET collapsed group labels (`.fi-sidebar-group-collapsed-label`), collapse CTA crossfade (`.fi-sidebar-collapse-morph`, open/close buttons, `.fi-sidebar-collapse-toggle-label`), and `.fi-sidebar-animating` enter keyframes. Open/collapsed visibility snaps instantly with no content delay.

Navigation group open/close (Tools, Integrations, etc.) uses Alpine `x-collapse` on `.fi-sidebar-group-items` and Filament dropdown fade on `.fi-sidebar .fi-dropdown-panel` / `.tido-sidebar-flyout-panel`. `transition: none !important` zeroes computed duration so Alpine finishes collapse and flyout show/hide immediately (chevron rotation on `.fi-sidebar-group-collapse-btn` included).

## Agent checklist

1. New decorative animation: respect both `@media (prefers-reduced-motion: reduce)` **and** `html.tido-reduce-motion`
2. JS motion gates: use `window.tidoPrefersReducedMotion()` when available
3. Do not fork `xplodman/filament-count-up` — rely on the `matchMedia` wrap
4. Profile toggle: native `Toggle`, not a custom Blade control — see [ui-custom-toggles.md](ui-custom-toggles.md)
5. Tests: [`ReduceMotionProfileTest.php`](../tests/Feature/ReduceMotionProfileTest.php)

## Related

- [ui-count-up.md](ui-count-up.md) — animated numeric values
- [ui-text-marquee.md](ui-text-marquee.md) — looping overflow text
- [ui-section-nav.md](ui-section-nav.md) — smooth scroll section tabs
- [ui-custom-toggles.md](ui-custom-toggles.md) — when custom toggles are required
