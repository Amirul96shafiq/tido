# Field character limits

Canonical Filament contract for max length + live `current/max` counters on tido admin text fields.

**Do not** hand-roll `maxLength()` plus a one-off hint. Use `TextInput::characterLimit()` or inherit the `NotesRichEditor` default so the counter, HTML `maxlength`, and Laravel `max` rule stay in sync.

DB columns stay wide (`varchar` / `text`). The cap is application-level (forms + ingest truncation).

## Pieces

| Piece | Path |
|-------|------|
| Constants + apply helpers | [`app/Support/FieldCharacterLimits.php`](../app/Support/FieldCharacterLimits.php) |
| TextInput macro | `TextInput::characterLimit()` in [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) |
| Notes default | [`NotesRichEditor`](../app/Filament/Forms/Components/NotesRichEditor.php) `setUp()` |
| Counter CSS | `.fi-character-count` in [`resources/css/app.css`](../resources/css/app.css) |
| OCR truncate | [`ReceiptParseNormalizer`](../app/Services/ReceiptParseNormalizer.php) |
| WhatsApp text truncate | [`ManualWhatsAppExpenseParser`](../app/Support/ManualWhatsAppExpenseParser.php) |
| Tests | `FieldCharacterLimitsTest`, `FieldCharacterLimitsFormTest`, `ReceiptParseNormalizerTest`, `ManualWhatsAppExpenseParserTest` |

## UX

- Counter format: `{current}/{max}` (example `10/20`)
- Placement: **inside** the field, right side
  - **TextInput**: Filament inline `suffix()` in the same bordered wrapper (flex sibling of the input, so typed text cannot overlap the count)
  - **NotesRichEditor**: bottom-right inside the editor canvas, with extra content padding
- **TextInput**: HTML `maxlength` hard-stop + Laravel `max` + live JS code-point count (`[...String($state ?? '')].length`)
- **NotesRichEditor**: plaintext count (TipTap `getText()` / JSON `text` nodes), not HTML tags. Save is blocked over the limit; the editor does not hard-stop typing
- Impersonal copy — see [ui-copy-style.md](ui-copy-style.md)

## Limits

| Surface | Field | Constant | Max |
|---------|-------|----------|-----|
| Profile / Family Member | Full name | `USER_NAME` | 25 |
| Profile / Family Member | Display name | `DISPLAY_NAME` | 20 |
| Family Member | Custom relationship | `RELATIONSHIP_OTHER` | 20 |
| Expense | Merchant name | `MERCHANT_NAME` | 80 |
| Expense | Line item description | `LINE_ITEM_DESCRIPTION` | 80 |
| Budget | Appearance title | `BUDGET_TITLE` | 30 |
| Recurring | Details title | `RECURRING_TITLE` | 30 |
| Label | Details name | `LABEL_NAME` | 30 |
| Payment Method | Details name | `PAYMENT_METHOD_NAME` | 30 |
| Notes (all `NotesRichEditor` fields) | plaintext | `NOTES` | 100 |

System label `Subscriptions & Memberships` (27) fits `LABEL_NAME`.

## Contract

```php
use App\Support\FieldCharacterLimits;

TextInput::make('display_name')
    ->characterLimit(FieldCharacterLimits::DISPLAY_NAME);

TextInput::make('title')
    ->characterLimit(
        FieldCharacterLimits::BUDGET_TITLE,
        'Auto-fills from the Label when empty. Clear to use the Label name at display time.',
    );
```

Pass helper copy as the second argument when the field already had `helperText()`. The counter is an inline suffix; helper text still uses `helperText()` below the field.

`NotesRichEditor::make('notes')` already applies `NOTES` (100). Do not add a second `maxLength()`. When the field uses `hiddenLabel()`, still set an explicit `->label()` (matching the section title) so the accessible name does not fall back to the column name.

Ingest writers that skip Filament must truncate with `FieldCharacterLimits::truncate($value, FieldCharacterLimits::MERCHANT_NAME)` (or the matching constant).

## Adding a new text field

1. Add a named constant on `FieldCharacterLimits` (do not reuse an unrelated constant even if the number matches today).
2. TextInput: `->characterLimit(FieldCharacterLimits::FOO)`. Notes: use `NotesRichEditor` and inherit 100.
3. If helper text is required, pass it as the second `characterLimit()` argument — do not also call `helperText()`.
4. Truncate every non-Filament writer (OCR, WhatsApp, jobs, commands) with `FieldCharacterLimits::truncate()`.
5. Keep factories at or under the cap.
6. Pest: `getMaxLength()` on the schema component, plus Livewire at-limit save and over-limit `assertHasFormErrors`.
7. After CSS changes: `npm run dev` / `npm run build`.

### Agent checklist

- [ ] Constant lives on `FieldCharacterLimits`, not a magic number in the form
- [ ] `characterLimit()` / `NotesRichEditor` default — no one-off counter markup
- [ ] Helper text composed via the second argument when needed (not `helperText()` plus a second `belowContent`)
- [ ] Hidden notes fields still set `->label()` before `hiddenLabel()`
- [ ] Non-Filament writers truncated to the same constant
- [ ] Factory values fit the cap
- [ ] `getMaxLength()` + over-limit validation tests
- [ ] Counter class stays `fi-character-count`

## Not in scope

| Field | Why |
|-------|-----|
| Phone numbers | Format validation, not identity length |
| Slugs, aliases, invoice numbers, serials | Matching / identifiers, not display names |
| Money fields | `->myr()` numeric contract |
| Danger Zone confirmation phrases | Exact-match phrases, not free text |
| Search inputs | Unrelated to record identity |

## Related

- Notes editor: [ui-notes-rich-editor.md](ui-notes-rich-editor.md)
- Empty placeholders: [ui-form-empty-defaults.md](ui-form-empty-defaults.md)
- Filament conventions: `.cursor/rules/filament-conventions.mdc`
