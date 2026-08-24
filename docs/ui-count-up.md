# Count Up Numbers

Count Up is a progressive enhancement for numeric UI values. It animates numbers from zero when they enter the viewport, while preserving the final server-rendered value when JavaScript is unavailable.

## Package and registration

The package is `xplodman/filament-count-up`. It is registered on the `admin` panel in [`AdminPanelProvider.php`](../app/Providers/Filament/AdminPanelProvider.php):

```php
CountUpPlugin::make()
    ->autoAnimateStats(false)
```

Automatic plugin view replacement is disabled because tido has a local Stats Overview view override for marquee descriptions and chart markup. Count Up is called explicitly in the local views so those custom behaviors remain intact.

## Which component to use

### Filament Stats Overview values

Use `CountUpStat::animate()` when the value contains text, a currency prefix, or a suffix:

```blade
{!! \Xplodman\CountUp\Facades\CountUpStat::animate(MoneyDisplay::withPrefix($amount)) !!}
```

This is used by the local Stats Overview override and preserves values such as `RM 1,234.50`, percentages, and units.

### Custom Blade values

Use `<x-count-up>` for a raw numeric value:

```blade
<x-count-up :value="$percentage" :decimals="1" suffix="% consumed" />
```

Use `prefix` and `suffix` for static surrounding text:

```blade
<x-count-up :value="$rate" :decimals="4" prefix="1 USD = RM " />
```

### Table columns

Use `CountUpColumn` for non-null numeric table columns:

```php
CountUpColumn::make('amount')
    ->countUpDecimals(2)
    ->countUpPrefix('RM ');
```

For records with nullable amounts or custom placeholders, do not use `CountUpColumn`: the package converts blank state to zero. Use a normal `TextColumn` and return `CountUpStat::animate()` only for numeric states so placeholders such as `Variable` remain correct.

## Current usage

Count Up is used in:

- Finances Stats Overview values
- Budget Performance rows and form previews
- Budget amounts
- Expense totals and discounts
- Recent Receipts totals
- Receipt Upload totals
- Current Currency rate, low, high, and average values
- Recurring Month Snapshot amounts and paid count
- Recurring Payment Dues amounts
- Recurrings table expected amounts, with nullable `Variable` support

## Formatting rules

- Keep server-rendered final text correct before Alpine hydrates.
- Use two decimals for MYR amounts.
- Use the existing `MoneyDisplay` helper for currency formatting.
- Use one decimal for budget percentages unless the surrounding UI specifies otherwise.
- Do not animate IDs, dates, labels, timestamps, or decorative chart values.
- Keep `wire:key` behavior enabled for Livewire values that should replay after refresh.
- Respect `prefers-reduced-motion`; the package skips animation automatically.

## Verification

When adding a new Count Up value:

1. Add or update a focused Pest assertion for the final formatted value.
2. Assert `fi-count-up` or `x-data="countUp(...)"` when testing rendered markup.
3. Verify empty, nullable, placeholder, currency, and percentage states.
4. Run the affected test file and `vendor/bin/pint --dirty --format agent`.
5. Check the dashboard in light mode, dark mode, mobile layout, and with reduced motion enabled.
