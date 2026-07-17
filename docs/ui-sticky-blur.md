# UI sticky blur veil

Reusable pattern for a Filament schema bar that sticks to the **top** (below the panel topbar) or **bottom** of the viewport, with a frosted blur + tint veil behind it while pinned.

- **Top reference:** dashboard month filter in [`app/Filament/Pages/Dashboard.php`](../app/Filament/Pages/Dashboard.php)
- **Bottom form CTAs:** trait [`HasStickyBlurFormActions`](../app/Filament/Concerns/HasStickyBlurFormActions.php) on Create/Edit resource pages and profile

## Why Filament needs a special structure

`position: sticky` is constrained by its **parent box**. Filament wraps each schema child as:

```text
.tido-sticky-scope
  > .fi-sc.fi-grid          ← tall (pin + scrolling content)
    > .fi-grid-col          ← short when it only wraps the pin ← sticky goes HERE
      > .tido-sticky-marker
    > .fi-grid-col          ← scrolling widgets / tables / etc.
```

Put sticky CSS on the short `.fi-grid-col` that contains the marker. That column’s parent grid must span both the pin and the content that scrolls away.

Also clear layout overflow or sticky never engages:

```css
.fi-layout:has(.tido-sticky-scope) {
    overflow-x: visible;
}
```

(Already in [`resources/css/app.css`](../resources/css/app.css).)

## Hook classes

| Class | Where | Role |
|-------|--------|------|
| `tido-sticky-scope` | Outer `Group` wrapping pin + content | Tall sticky containing block |
| `tido-sticky-marker` | Inner `Group` around the pin only | Marks which grid-col to stick |
| `tido-sticky-marker--top` | Same as marker | Stick below topbar + top veil |
| `tido-sticky-marker--bottom` | Same as marker | Stick above viewport bottom + bottom veil |
| `tido-sticky-stuck` | Applied by JS on the sticky `.fi-grid-col` | Shows the blur veil |

Do not put `position: sticky` on the marker itself — CSS targets:

```text
.tido-sticky-scope > .fi-sc > .fi-grid-col:has(.tido-sticky-marker--)
```

## Filament usage (top)

```php
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

public function content(Schema $schema): Schema
{
    return $schema->components([
        Group::make([
            Group::make([
                // pin UI: filters, toolbar, actions…
                $this->getFiltersFormContentComponent(),
            ])->extraAttributes([
                'class' => 'tido-sticky-marker tido-sticky-marker--top',
            ]),
            // scrolling body (widgets, table, sections…)
            $this->getWidgetsContentComponent(),
        ])->extraAttributes([
            'class' => 'tido-sticky-scope',
        ]),
    ]);
}
```

## Filament usage (bottom)

Same structure; swap the edge modifier:

```php
])->extraAttributes([
    'class' => 'tido-sticky-marker tido-sticky-marker--bottom',
]),
```

Place the pin Group **after** the scrolling content if the bar should sit at the bottom of the page flow, or keep order as needed — sticky `bottom` still pins to the viewport when scrolling.

## Form actions (bottom)

Create / Edit resource pages and the profile page use the shared trait [`HasStickyBlurFormActions`](../app/Filament/Concerns/HasStickyBlurFormActions.php) so form CTAs (Create, Create & create another, Save changes, Cancel, and any other `getFormActions()` buttons) stick to the viewport bottom with the same blur veil.

Do **not** use Filament’s `stickyFormActions()` — that applies an opaque fixed card, not the tido frosted veil.

### Current opt-in pages

| Page | Notes |
|------|--------|
| `CreateInvoice` / `EditInvoice` | Resource CTAs |
| `CreateLabel` / `EditLabel` | Resource CTAs |
| `CreateBudget` / `EditBudget` | Resource CTAs |
| `Auth/EditProfile` | Save / Cancel / Danger Zone CTAs |

### Opt in a new Create / Edit page

1. On the page class: `use HasStickyBlurFormActions;`
2. Keep CTAs in `getFormActions()` so they land in the shared sticky bar
3. Do **not** call `CreateRecord::stickyFormActions()` / `EditRecord::stickyFormActions()` / `BasePage::stickyFormActions()`
4. Extend the dataset in `tests/Feature/StickyBlurFormActionsTest.php`
5. No per-page CSS or JS — panel assets already load the veil

Dashboard remains the reference for **top** sticky bars (manual `Group` + markers). Form pages use the trait for **bottom** CTAs.

## Assets (already panel-wide)

- CSS: [`resources/css/app.css`](../resources/css/app.css) — sticky offsets, progressive blur/tint veil (dual `::before` / `::after` layers)
- JS: [`resources/js/sticky-blur-veil.js`](../resources/js/sticky-blur-veil.js) — rAF scroll listener; toggles `tido-sticky-stuck`
- Registered in [`vite.config.js`](../vite.config.js) and [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php) `->assets()`

No per-page JS registration is required once the hook classes are present.

## Behaviour notes

- **Top offset** matches tido topbar height: `calc(var(--collapsed-sidebar-width, 4.5rem) - 1px + 0.25rem)` (`gap-1` under the topbar).
- **Bottom offset** uses `0.25rem` from the viewport bottom.
- Veil is **fixed**, full width, only visible while `.tido-sticky-stuck`. Progressive dual-layer blur + slate/white tint avoids Chromium’s hard `backdrop-filter` mask cutoff.
- Pin children stay sharp (`z-index: 1` above the veil).
- SPA: JS re-binds on `livewire:navigated`.

## Checklist for a new sticky bar

1. Wrap pin + scrolling content in `Group` with `tido-sticky-scope`.
2. Wrap the pin alone in an inner `Group` with `tido-sticky-marker` + `--top` or `--bottom`.
3. Confirm `.fi-layout` overflow fix applies (automatic when scope is present).
4. Hard-refresh and scroll: pin sticks; veil fades in only while stuck.
5. Rebuild assets if CSS/JS changed: `npm run build` (or `npm run dev`).

## Do not

- Stick an element whose parent is only as tall as the pin (veil/sticky will never hold).
- Rely on `.page-class .fi-layout` descendant selectors for overflow — page classes sit *inside* `.fi-layout`.
- Put opaque backgrounds on the pin that defeat the frosted look unless intentional.
- Invent a second stuck-detection script — extend `sticky-blur-veil.js` if needed.
- Use Filament `stickyFormActions()` for Create/Edit/profile CTAs — use `HasStickyBlurFormActions` instead.
