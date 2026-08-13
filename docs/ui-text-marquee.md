# Single-line text overflow patterns

Use [`x-tido.single-line-text`](../resources/views/components/tido/single-line-text.blade.php) for new single-line labels. It keeps text on one line, fades the right edge while idle, and pans once toward the hidden end of the text on hover.

```blade
<x-tido.single-line-text
    class="flex-1"
    text-class="font-semibold text-gray-800 dark:text-gray-200"
>
    {{ $label }}
</x-tido.single-line-text>
```

Pass layout classes to the component and text styling through `text-class`. The component measures its own overflow with `ResizeObserver`, including after responsive layout changes and Livewire morphs.

## Hover-pan component

Use the reusable component for account names, chat titles, navigation labels, and other short labels that must remain on one line:

1. Text stays still with a right-edge fade when not hovered.
2. Hovering waits briefly, then pans to the hidden end of the text.
3. Leaving the text resets it to the beginning.
4. Reduced-motion preferences disable the pan.

Do **not** use for body copy, multi-line descriptions, or primary page headings.

## Continuous marquee

Use [`x-tido.text-marquee`](../resources/views/components/tido/text-marquee.blade.php) for new continuous-scrolling surfaces. It measures overflow, duplicates the slot for a seamless loop, and respects reduced motion. Prefer `x-tido.single-line-text` for hover-pan labels unless continuous motion is specifically required.

```blade
<x-tido.text-marquee
    class="min-w-0 flex-1"
    text-class="inline-flex items-center gap-2 whitespace-nowrap"
>
    <span class="font-semibold">{{ $title }}</span>
    <span class="…pill…">{{ $cadence }}</span>
</x-tido.text-marquee>
```

The older inline `.tido-text-marquee` CSS animation remains only for Filament JS Select selected values, which Filament recreates in the DOM and cannot host a Blade component.

**Canonical first uses:**

| Surface | Path |
|---------|------|
| Recurring Payment Dues (title + cadence pill, description) | [`resources/views/filament/widgets/due-recurrings.blade.php`](../resources/views/filament/widgets/due-recurrings.blade.php) |
| Budget Performance widget titles | [`resources/views/filament/widgets/budget-status.blade.php`](../resources/views/filament/widgets/budget-status.blade.php) |
| Monthly spending stats descriptions | [`resources/views/vendor/filament-widgets/stats-overview-widget/stat.blade.php`](../resources/views/vendor/filament-widgets/stats-overview-widget/stat.blade.php) |
| Filament JS Select selected value (CSS animation helper) | Expense `currency` via [`SelectValueMarquee`](../app/Filament/Support/SelectValueMarquee.php) |

**Shared CSS:** [`.tido-text-marquee`](../resources/css/app.css) / [`.tido-text-marquee-track`](../resources/css/app.css) in `resources/css/app.css`  
**Select helper JS:** [`resources/js/select-value-marquee.js`](../resources/js/select-value-marquee.js) (panel asset)

## When to use the continuous marquee

Apply this pattern when **all** of these are true:

- The label must stay on **one line**
- The row is narrow (widget column, mobile, sidebar) and siblings must not wrap
- Truncation with ellipsis alone is not enough — users need to read the full string without hover
- Motion is acceptable (respect `prefers-reduced-motion`)

Do **not** use for body copy, multi-line descriptions, or primary page headings.

## Continuous marquee contract

Blade surfaces use [`x-tido.text-marquee`](../resources/views/components/tido/text-marquee.blade.php):

| Token | Role |
|-------|------|
| `.tido-text-marquee-clip` | Outer clip: `relative min-w-0 overflow-hidden` |
| `.tido-text-marquee-track` | Inner track translated by Alpine `requestAnimationFrame` |
| `.tido-text-marquee-segment` | Slot copy; a duplicate is hidden until overflowing |
| `x-ref="marqueeTrack"` / `x-ref="marqueeSegment"` | Measured by the component |
| `whitespace-nowrap` | Required on the segment (`text-class`) |
| `min-w-0` | Required on clip **and** flex ancestors so width can shrink |

Filament JS Select still uses the CSS animation helper:

| Token | Role |
|-------|------|
| `--tido-marquee-clip` | CSS var = clip `clientWidth` in `px` (select JS) |
| `.tido-text-marquee` | Animation class — added **only** when overflowing |
| `.tido-select-value-marquee` | Opt-in on Filament JS `Select` fields |

Shared Select keyframes (already in `app.css`):

```css
@keyframes tido-text-marquee {
    0%, 15% { transform: translateX(0); }
    85%, 100% { transform: translateX(calc(-100% + var(--tido-marquee-clip, 9rem))); }
}

.tido-text-marquee {
    animation: tido-text-marquee 8s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .tido-text-marquee { animation: none; }
}
```

Do not duplicate this CSS under a new class name. Reuse `.tido-text-marquee-track` for Blade surfaces and `.tido-text-marquee` / `--tido-marquee-clip` only for Filament JS Select.

## Continuous marquee Blade

Budget Performance on mobile keeps icon + title/period on the left and spent/total stacked on the right of the same row. Period sits under the title; from `sm` up, title + period and spent + total return to inline rows. The title clip uses `flex-1 min-w-0` (no fixed `max-w-*`).

```blade
{{-- Mobile: icon | title/period | spent/total (two columns). sm+: inline siblings --}}
<div class="flex min-w-0 items-start justify-between gap-2 sm:items-center">
    <div class="flex min-w-0 flex-1 items-center gap-2">
        <span class="shrink-0">{{-- icon --}}</span>

        <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2">
            <x-tido.text-marquee
                class="min-w-0 flex-1"
                text-class="inline-block font-semibold whitespace-nowrap …"
            >{{ $label }}</x-tido.text-marquee>

            <span class="w-fit shrink-0 whitespace-nowrap">{{-- period — below title on mobile --}}</span>
        </div>
    </div>

    <div class="flex shrink-0 flex-col items-end gap-0.5 whitespace-nowrap text-right sm:flex-row sm:items-baseline sm:gap-1">
        <span>{{-- spent (top on mobile) --}}</span>
        <span>{{-- / total (bottom on mobile) --}}</span>
    </div>
</div>
```

### Continuous marquee checklist when applying a new Blade surface

1. Identify the text that wraps on narrow widths.
2. Wrap it in [`x-tido.text-marquee`](../resources/views/components/tido/text-marquee.blade.php).
3. Choose `max-w-*` for that surface (or rely on `flex-1 min-w-0` without a fixed max if the clip should fill leftover space). On dense mobile rows, put fixed meta (`shrink-0`) beside the title group — stack period under the title and spent over total so the clip is not crushed.
4. Mark siblings that must stay visible (`badge`, amounts, icon buttons) with `shrink-0` and `whitespace-nowrap` where needed.
5. Ensure every flex parent up to the clip has `min-w-0`.
6. Do **not** copy a second keyframes block or inline Alpine `marqueeText` measure — use the shared component.
7. After CSS changes with Vite running, hard-refresh (HMR). New `Vite::asset()` panel JS entries need `npm run build` once — see [`vite-assets.md`](vite-assets.md).
8. Add/extend a Pest feature test asserting `tido-text-marquee-clip`, `tido-text-marquee-track`, `x-ref="marqueeSegment"`, and `x-ref="marqueeTrack"` appear in the rendered HTML.

## Filament JS Select (selected value)

Use this when a searchable / JS Filament `Select` shows a long option label that wraps inside a narrow column (e.g. `MYR (Malaysian Ringgit)`).

**Do not** invent a field-specific class (`fi-currency-select`, etc.). Always opt in with the shared token.

### Pieces

| Piece | Path / value |
|-------|----------------|
| PHP constant | `App\Filament\Support\SelectValueMarquee::EXTRA_CLASS` → `tido-select-value-marquee` |
| Helper | `SelectValueMarquee::extraAttributes()` |
| CSS | `.tido-select-value-marquee …` in `resources/css/app.css` |
| JS | `resources/js/select-value-marquee.js` (registered in `AdminPanelProvider` via `Vite::asset` — see [`vite-assets.md`](vite-assets.md)) |

### Drop-in

```php
use App\Filament\Support\SelectValueMarquee;
use Filament\Forms\Components\Select;

Select::make('currency')
    ->options([
        'MYR' => 'MYR (Malaysian Ringgit)',
    ])
    ->searchable()
    ->wrapOptionLabels(false) // keep the closed value on one line
    ->extraAttributes(SelectValueMarquee::extraAttributes());
```

Requirements:

1. Field must be a **JS** select (`searchable()`, `multiple()`, `native(false)`, or `allowHtml()` — not a plain native `<select>`).
2. Call `wrapOptionLabels(false)` so the **closed** selected value stays on one line (marquee handles overflow). Dropdown options still show the **full** label (no ellipsis) via shared CSS under `.tido-select-value-marquee`.
3. Use `SelectValueMarquee::extraAttributes()` (or `['class' => SelectValueMarquee::EXTRA_CLASS]` with `merge: true` if merging other attributes).
4. Rebuild after CSS changes with Vite running (HMR). After adding/renaming the panel JS entry, run `npm run build` once — see [`vite-assets.md`](vite-assets.md).

### Agent checklist (Select)

- [ ] `wrapOptionLabels(false)`
- [ ] `SelectValueMarquee::extraAttributes()` — no one-off class names
- [ ] Test asserts `tido-select-value-marquee` and `canOptionLabelsWrap() === false`
- [ ] Do **not** duplicate select-marquee CSS/JS under a new name

## Flex row recipe (with siblings)

```blade
{{-- Dense mobile: period under title; spent over total — same row as icon --}}
<div class="flex min-w-0 items-start justify-between gap-2 sm:items-center">
    <div class="flex min-w-0 flex-1 items-center gap-2">
        <span class="shrink-0">{{-- icon --}}</span>

        <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2">
            {{-- marquee clip: flex-1 min-w-0 (fill leftover) --}}
            <span class="w-fit shrink-0 whitespace-nowrap">{{-- period — below title on mobile --}}</span>
        </div>
    </div>

    <div class="flex shrink-0 flex-col items-end gap-0.5 whitespace-nowrap text-right sm:flex-row sm:items-baseline sm:gap-1">
        {{-- spent (top) / total (bottom) on mobile; inline from sm --}}
    </div>
</div>
```

## Continuous marquee behaviour details

1. Text that fits the clip stays static (duplicate segment hidden; no animation).
2. Overflowing Blade text loops continuously via `requestAnimationFrame` on `.tido-text-marquee-track`.
3. Overflowing Select values use the CSS keyframes: hold at start → scroll RTL → hold at end → restart, with `--tido-marquee-clip` so the last characters stop flush with the clip edge.
4. `ResizeObserver` re-measures after Livewire morph, sidebar collapse, and viewport resize.
5. `prefers-reduced-motion: reduce` disables animation; text remains single-line clipped.
6. Select helper also uses `MutationObserver` because Filament recreates the selected-label DOM when the value changes.
7. For Select marquee fields: dropdown option labels show in full (wrap allowed); only the closed selected value is single-line + marquee.

## Choosing clip width

| Approach | When |
|----------|------|
| `flex-1 min-w-0` without fixed max | Label should take all leftover space between fixed siblings (Budget Performance) |
| Fixed `max-w-[9rem] sm:max-w-[12rem]` | Dense widgets when you intentionally cap title width |
| Smaller `max-w-*` | Very narrow columns (e.g. mobile list tiles) |
| Select field width | Clip is the Filament `.fi-select-input-value-ctn` — no fixed `max-w-*` needed |

Tune per surface; keep the Alpine/CSS contract identical.

## Tests

### `x-tido.text-marquee`

```php
->assertSee('tido-text-marquee-clip', false)
->assertSee('tido-text-marquee-track', false)
->assertSee('x-ref="marqueeSegment"', false)
->assertSee('x-ref="marqueeTrack"', false)
->assertDontSee('x-ref="marqueeText"', false)
```

Reference: [`tests/Feature/DueRecurringsWidgetTest.php`](../tests/Feature/DueRecurringsWidgetTest.php) → `due recurrings widget uses payment-card two-zone layout`. Also [`tests/Feature/BudgetStatusWidgetTest.php`](../tests/Feature/BudgetStatusWidgetTest.php) → `budget status widget uses single-line title marquee markup`.

### Filament Select

```php
->assertSee(SelectValueMarquee::EXTRA_CLASS, false)
->assertSchemaComponentExists(
    'currency',
    checkComponentUsing: fn (Select $component): bool => ! $component->canOptionLabelsWrap(),
);
```

Reference: [`tests/Feature/ExpenseFormReceiptImageTest.php`](../tests/Feature/ExpenseFormReceiptImageTest.php) → `expense currency select uses single-line marquee markup`.

## Do not

- Invent a second marquee class or keyframes set
- Invent field-specific select marquee classes (e.g. `fi-currency-select`)
- Copy the old inline Alpine `marqueeText` / `--tido-marquee-clip` block into new Blade surfaces — use `x-tido.text-marquee`
- Animate when `scrollWidth <= clientWidth`
- Drop `ResizeObserver` (Livewire/layout changes break the measure)
- Allow the marquee text to wrap (`break-words`, multi-line)
- Use browser `title=` as the only way to reveal truncated icon CTAs — see [ui-tooltips.md](ui-tooltips.md)
- Apply to long paragraphs or multi-line body copy
- Use select marquee on native (non-JS) Filament selects — it targets `.fi-select-input-value-label`
