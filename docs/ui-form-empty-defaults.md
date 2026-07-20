# Resource form empty defaults

Empty Create/Edit fields should never look like a blank void. Use Filament **placeholders** for UI hints, and **defaults** (optionally restored on blur) when a real value must be present.

## When to use which

| Pattern | API | Saved on submit? | Use when |
|---------|-----|------------------|----------|
| Placeholder | `->placeholder('…')` | No | Identity / money fields the user must fill; empty state shows a hint only |
| Default | `->default(…)` | Yes (if unchanged) | Sensible starting values (period, year, optional money `0.00`, quantity `1`) |
| Default + restore | `->default(…)` + `->live(onBlur: true)` + `afterStateUpdated` when `blank($state)` | Yes | Required fields that should never stay empty after blur (e.g. line item description / line total) |

Do **not** use restore-on-empty for fields where a placeholder string would pollute real data (merchant name, label name, budget amount). Prefer `placeholder()` there.

## Money fields (MYR)

- Optional / secondary amounts that may start at zero: `->myr()->default(0.00)` (e.g. tax, discount, rounding).
- Required primary amounts the user must enter: `->myr()->placeholder('0.00')` (e.g. invoice subtotal / total, budget limit amount).
- Repeater line totals that drive collapsed labels: `->myr()->default('0.00')` + restore when blank on blur.

Always use the `->myr()` macro (`MoneyDisplay`) — do not hand-roll prefixes or decimal formatting.

## Checklist for a new resource form

When adding or extending `app/Filament/Resources/{Plural}/Schemas/{Singular}Form.php`:

1. Scan every `TextInput` (and similar) for empty Create-state UX.
2. Add `placeholder()` for required identity / amount fields that stay empty until the user types.
3. Add `default()` for enums, booleans, years, quantities, and optional money that should start at `0.00`.
4. Only add restore-on-blur when an empty value breaks UX (e.g. repeater item labels) and the restored value is acceptable to save.
5. Cover with a Pest assertion (`getPlaceholder()` / `getDefaultState()` / Livewire `set` + `assertSet` for restore).
6. Keep copy impersonal — see [ui-copy-style.md](ui-copy-style.md).

## Current examples

| Resource | Field | Pattern | Value |
|----------|-------|---------|-------|
| Invoice | `merchant_name`, `invoice_number` | Placeholder | `Merchant name`, `Invoice number` |
| Invoice | `subtotal`, `total_amount` | Placeholder | `0.00` |
| Invoice | `total_tax`, `discount_total`, `rounding_amount` | Default | `0.00` |
| Invoice item | `description` | Default + restore | `Item name` |
| Invoice item | `line_total` | Default + restore | `0.00` |
| Budget | `amount` (Limit & Period) | Placeholder | `0.00` |
| Budget | `period`, `year` | Default | `monthly`, current year |
| Label | `name`, `slug` | Placeholder | `Label name`, `label-slug` |
| Payment Method | `name`, `slug`, `aliases` | Placeholder | `Payment method name`, `payment-method-slug`, `Add alias (e.g. grabpay)` |

## References

- Forms: `InvoiceForm`, `BudgetForm`, `LabelForm`, `PaymentMethodForm` under `app/Filament/Resources/*/Schemas/`
- Tests: `tests/Feature/InvoiceFormReceiptImageTest.php`, `BudgetFormTest.php`, `LabelFormTest.php`, `PaymentMethodFormTest.php`
- Filament docs: field `placeholder()` vs `default()` (Boost `search-docs`)
