# Vite panel assets

How tido registers Filament panel JS/CSS with Vite, and when `npm run build` is required.

`public/build` is gitignored. Local and deploy environments must build (or run the Vite dev server) themselves.

## Two registration paths

| Path | Typical use | Resolves via |
|------|-------------|--------------|
| `@vite(...)` / Blade | `resources/css/app.css` (render hook in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php)) | Vite HMR when `public/hot` exists; otherwise the production manifest |
| `Vite::asset(...)` | Extra panel scripts in `AdminPanelProvider` `->assets()` (`Js::make(...)`) | Same: `public/hot` **or** [`public/build/manifest.json`](../public/build/manifest.json) |

Panel scripts (swipe dismiss, sticky blur veil, Tippy mobile disable, marquees, upload handlers, etc.) use **`Vite::asset()`**. They must be listed in [`vite.config.js`](../vite.config.js) `input` **and** registered in `AdminPanelProvider`.

## Scripts (keep separate)

| Command | Role |
|---------|------|
| `npm run dev` | Vite **dev server** (HMR); writes `public/hot` |
| `npm run build` | One-shot production build; writes `public/build/manifest.json` + hashed assets |
| `npm run dev:full` / `dev:all` | Concurrent Vite + PHP (+ queue / Evolution / Ollama). Does **not** run `build` |

Do **not** fold `build` into every `dev` / `dev:all` start. Do **not** treat `build` and `dev` as interchangeable for **new** `Vite::asset()` entry paths.

## When `npm run build` is required

Run **once** after:

- Adding a new file to `vite.config.js` `input` that is loaded with `Vite::asset(...)`
- Renaming or removing such an entry (so the manifest stays accurate)

### Checklist for a new panel JS entry

1. Add `resources/js/your-script.js`
2. Register it in [`vite.config.js`](../vite.config.js) `input`
3. Register in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php):

```php
Js::make(
    'your-script',
    Vite::asset('resources/js/your-script.js'),
)->module(),
```

4. Run `npm run build` once (updates the gitignored manifest)
5. Continue with `npm run dev` / `dev:full` / `dev:all` as usual

## When build is not required

- Editing the **contents** of an existing Vite entry while `npm run dev` is running (HMR / hot URL)
- CSS-only changes under `@vite('resources/css/app.css')` with the Vite dev server up — `npm run build` **or** `npm run dev` both work

## Why `dev:all` can still crash

`Vite::asset()` behaviour:

1. If `public/hot` exists → serve from the Vite dev server (no manifest lookup)
2. Else → look up the asset in `public/build/manifest.json`

`dev:full` / `dev:all` start Vite and PHP/queue **in parallel**. PHP may boot `AdminPanelProvider` (eager `Vite::asset()` calls) **before** Vite writes `public/hot`. Laravel then uses the production manifest.

If that manifest is **stale** (missing a newly added entry), artisan fails with:

```text
Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest: resources/js/...
```

**Fix:** run `npm run build` to refresh the manifest. Starting `npm run dev` alone does **not** write missing production-manifest entries.

## ViteException quick fix

For missing-file errors on paths registered via `Vite::asset()`:

1. Confirm the file exists and is in `vite.config.js` `input`
2. Confirm `AdminPanelProvider` registers `Vite::asset('resources/js/…')`
3. Run `npm run build`
4. Restart `npm run dev:full` / `dev:all` if needed

## Related

- Sticky blur panel JS: [`ui-sticky-blur.md`](ui-sticky-blur.md)
- Select value marquee JS: [`ui-text-marquee.md`](ui-text-marquee.md)
- Mobile Tippy disable JS: [`ui-tooltips.md`](ui-tooltips.md)
