# UI text marquee (overflow scroll)

Reusable pattern for **single-line** labels that continuously scroll right-to-left **only when** the text is wider than its clip. Use anywhere a flex/grid row would otherwise wrap long names into multi-line text and crowd siblings (badges, amounts, actions).

**Canonical first uses:**

| Surface | Path |
|---------|------|
| Budget Performance widget (Blade + Alpine) | [`resources/views/filament/widgets/budget-status.blade.php`](../resources/views/filament/widgets/budget-status.blade.php) |
| Filament JS Select selected value | Invoice `currency` via [`SelectValueMarquee`](../app/Filament/Support/SelectValueMarquee.php) |

**Shared CSS:** [`.tido-text-marquee`](../resources/css/app.css) in `resources/css/app.css`  
**Select helper JS:** [`resources/js/select-value-marquee.js`](../resources/js/select-value-marquee.js) (panel asset)

## When to use

Apply this pattern when **all** of these are true:

- The label must stay on **one line**
- The row is narrow (widget column, mobile, sidebar) and siblings must not wrap
- Truncation with ellipsis alone is not enough — users need to read the full string without hover
- Motion is acceptable (respect `prefers-reduced-motion`)

Do **not** use for body copy, multi-line descriptions, or primary page headings.

## Contract (do not invent a second pattern)

| Token | Role |
|-------|------|
| `.tido-text-marquee-clip` | Outer clip: `relative min-w-0 overflow-hidden` + a `max-w-*` (or flex shrink) |
| `--tido-marquee-clip` | CSS var = clip `clientWidth` in `px` (set by Alpine / select JS) |
| `x-ref="marqueeText"` | Inner text node measured via `scrollWidth` (Blade + Alpine only) |
| `.tido-text-marquee` | Animation class — added **only** when overflowing |
| `.tido-select-value-marquee` | Opt-in on Filament JS `Select` fields (selected-value marquee) |
| `whitespace-nowrap` | Required on the text span |
| `min-w-0` | Required on clip **and** flex ancestors so width can shrink |

Shared keyframes (already in `app.css`):

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

Do not duplicate this CSS under a new class name. Reuse `.tido-text-marquee` / `--tido-marquee-clip`.

## Drop-in Blade + Alpine

```blade
<div
    x-data="{ overflowing: false }"
    x-init="
        const measure = () => {
            $el.style.setProperty('--tido-marquee-clip', $el.clientWidth + 'px');
            overflowing = $refs.marqueeText.scrollWidth > $el.clientWidth;
        };
        measure();
        new ResizeObserver(measure).observe($el);
    "
    class="tido-text-marquee-clip relative min-w-0 max-w-[9rem] overflow-hidden sm:max-w-[12rem]"
>
    <span
        x-ref="marqueeText"
        class="inline-block whitespace-nowrap …"
        :class="{ 'tido-text-marquee': overflowing }"
    >{{ $label }}</span>
</div>
```

### Agent checklist when applying to a new Blade surface

1. Identify the text that wraps on narrow widths.
2. Wrap it in the clip + Alpine block above (keep `x-ref="marqueeText"`).
3. Choose `max-w-*` for that surface (or rely on `flex-1 min-w-0` without a fixed max if the clip should fill leftover space).
4. Mark siblings that must stay visible (`badge`, amounts, icon buttons) with `shrink-0` and `whitespace-nowrap` where needed.
5. Ensure every flex parent up to the clip has `min-w-0`.
6. Do **not** copy a second keyframes block — use `.tido-text-marquee`.
7. After CSS changes, rebuild Vite (`npm run build` / `npm run dev`).
8. Add/extend a Pest feature test asserting `tido-text-marquee-clip`, `x-ref="marqueeText"`, and `tido-text-marquee` appear in the rendered HTML.

## Filament JS Select (selected value)

Use this when a searchable / JS Filament `Select` shows a long option label that wraps inside a narrow column (e.g. `MYR (Malaysian Ringgit)`).

**Do not** invent a field-specific class (`fi-currency-select`, etc.). Always opt in with the shared token.

### Pieces

| Piece | Path / value |
|-------|----------------|
| PHP constant | `App\Filament\Support\SelectValueMarquee::EXTRA_CLASS` → `tido-select-value-marquee` |
| Helper | `SelectValueMarquee::extraAttributes()` |
| CSS | `.tido-select-value-marquee …` in `resources/css/app.css` |
| JS | `resources/js/select-value-marquee.js` (registered in `AdminPanelProvider`) |

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
4. Rebuild Vite after CSS/JS changes.

### Agent checklist (Select)

- [ ] `wrapOptionLabels(false)`
- [ ] `SelectValueMarquee::extraAttributes()` — no one-off class names
- [ ] Test asserts `tido-select-value-marquee` and `canOptionLabelsWrap() === false`
- [ ] Do **not** duplicate select-marquee CSS/JS under a new name

## Flex row recipe (with siblings)

```blade
<div class="flex min-w-0 items-center justify-between gap-2">
    <div class="flex min-w-0 flex-1 items-center gap-2">
        <span class="shrink-0">{{-- icon --}}</span>

        {{-- marquee clip here --}}

        <span class="shrink-0 whitespace-nowrap">{{-- badge --}}</span>
    </div>

    <div class="shrink-0 whitespace-nowrap text-right">
        {{-- amounts / meta that must not wrap --}}
    </div>
</div>
```

## Behaviour details

1. Text that fits the clip stays static (no animation class).
2. Overflowing text loops forever: hold at start → scroll RTL → hold at end → restart.
3. End position uses `--tido-marquee-clip` so the last characters stop flush with the clip edge (not fully off-screen).
4. `ResizeObserver` re-measures after Livewire morph, sidebar collapse, and viewport resize.
5. `prefers-reduced-motion: reduce` disables animation; text remains single-line clipped.
6. Select helper also uses `MutationObserver` because Filament recreates the selected-label DOM when the value changes.
7. For Select marquee fields: dropdown option labels show in full (wrap allowed); only the closed selected value is single-line + marquee.

## Choosing clip width

| Approach | When |
|----------|------|
| Fixed `max-w-[9rem] sm:max-w-[12rem]` | Dense widgets with important right-side meta (Budget Status) |
| `flex-1 min-w-0` without fixed max | Label should take all leftover space between fixed siblings |
| Smaller `max-w-*` | Very narrow columns (e.g. mobile list tiles) |
| Select field width | Clip is the Filament `.fi-select-input-value-ctn` — no fixed `max-w-*` needed |

Tune per surface; keep the Alpine/CSS contract identical.

## Tests

### Blade + Alpine

```php
->assertSee('tido-text-marquee-clip', false)
->assertSee('x-ref="marqueeText"', false)
->assertSee('tido-text-marquee', false)
->assertSee('whitespace-nowrap', false)
```

Reference: [`tests/Feature/BudgetStatusWidgetTest.php`](../tests/Feature/BudgetStatusWidgetTest.php) → `budget status widget uses single-line title marquee markup`.

### Filament Select

```php
->assertSee(SelectValueMarquee::EXTRA_CLASS, false)
->assertSchemaComponentExists(
    'currency',
    checkComponentUsing: fn (Select $component): bool => ! $component->canOptionLabelsWrap(),
);
```

Reference: [`tests/Feature/InvoiceFormReceiptImageTest.php`](../tests/Feature/InvoiceFormReceiptImageTest.php) → `invoice currency select uses single-line marquee markup`.

## Do not

- Invent a second marquee class or keyframes set
- Invent field-specific select marquee classes (e.g. `fi-currency-select`)
- Animate when `scrollWidth <= clientWidth`
- Drop `ResizeObserver` (Livewire/layout changes break the measure)
- Allow the marquee text to wrap (`break-words`, multi-line)
- Use browser `title=` as the only way to reveal truncated icon CTAs — see [ui-tooltips.md](ui-tooltips.md)
- Apply to long paragraphs or multi-line body copy
- Use select marquee on native (non-JS) Filament selects — it targets `.fi-select-input-value-label`
