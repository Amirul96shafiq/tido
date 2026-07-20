# UI notes rich editor

Canonical Filament field for note-like HTML columns in tido admin forms (`notes`, Label `description`, etc.).

**Do not** use stock `Textarea` or bare `RichEditor` for these fields. Use the shared component below so toolbar, height, and storage behaviour stay consistent.

## Pieces

| Piece | Path |
|-------|------|
| Component | `App\Filament\Forms\Components\NotesRichEditor` |
| CSS height | `.fi-notes-rich-editor` in `resources/css/app.css` (`min-height: 10rem`) |
| Constant | `NotesRichEditor::EXTRA_CLASS` → `fi-notes-rich-editor` |
| Tests | `BudgetFormTest`, `InvoiceFormReceiptImageTest`, `LabelFormTest`, `PaymentMethodFormTest`, `ContentDraftRecoveryTest`, `ReceiptExtractionPromptTest`, `ReceiptReparseTest` |

## Current consumers

| Form | Column | UI label |
|------|--------|----------|
| `BudgetForm` | `notes` | Hidden (section **Budget Notes**) |
| `InvoiceForm` | `notes` | **Invoice Notes** |
| `LabelForm` | `description` | **Label Notes** (column name kept for schema / OCR hints) |
| `PaymentMethodForm` | `notes` | Hidden (section **Payment Method Notes**) |

DB columns remain nullable `text` (HTML from the editor). Plain-text legacy rows still load.

## Contract (do not invent a second pattern)

```php
use App\Filament\Forms\Components\NotesRichEditor;

NotesRichEditor::make('notes') // or 'description' on Label
    // optional: ->hiddenLabel() / ->label('…') / ->columnSpanFull()
```

Defaults baked into `NotesRichEditor::setUp()`:

- Toolbar: bold, italic, underline, strike, link, bullet/ordered lists, undo/redo
- **No** `attachFiles` / tables (keep notes text-focused)
- Extra class `fi-notes-rich-editor` for shared min-height
- Stored as **HTML** (Filament RichEditor default — do **not** call `->json()` unless migrating columns + casts)

## Adding a new notes field

1. Prefer column name `notes` (or keep an existing note-like column such as Label `description`).
2. Use `NotesRichEditor::make('…')` in the Filament schema — do not copy toolbar arrays.
3. Keep the shared CSS class; change height only in `.fi-notes-rich-editor` in `app.css`.
4. If the page uses draft recovery (`RecoversContentDraft`), expect Livewire draft payloads to store TipTap **document JSON** for the field while the DB still receives **HTML** on create/save. Update assertions accordingly (see `ContentDraftRecoveryTest`).
5. Cover the field with a schema/component assertion (`assertSchemaComponentExists` with `NotesRichEditor`).
6. If the value is injected into AI prompts or other plain-text contexts, strip HTML first (see `ReceiptExtractionPrompt::plainTextHint`).

### Agent checklist

- [ ] Field name matches the Eloquent / migration column (not a display-only string passed to `make()`)
- [ ] `NotesRichEditor`, not `Textarea` / raw `RichEditor`
- [ ] No per-form duplicate toolbar or `fi-*-notes-editor` CSS classes
- [ ] Programmatic note appends (jobs/services) write HTML snippets, e.g. `<p>…</p>` — see `ExtractReceiptDataJob::appendDateReviewNote`
- [ ] When rendering notes outside Filament tables/infolists, sanitize (`str($html)->sanitizeHtml()` or Filament `RichContentRenderer`)
- [ ] Plain-text consumers (OCR prompts, WhatsApp text) strip tags before use
- [ ] Tests updated for HTML (and draft TipTap shape if applicable)
- [ ] After CSS change: `npm run dev` / `npm run build` so height is visible

## Not in scope

| Field | Why |
|-------|-----|
| Non-notes rich content | Use stock `RichEditor` with its own toolbar/CSS; do **not** force `fi-notes-rich-editor` |
| Invoice line-item `description` | Short line title — stays `TextInput`, not rich notes |

## Related

- Draft recovery: [content-draft-recovery.md](content-draft-recovery.md)
- Filament conventions: `.cursor/rules/filament-conventions.mdc`
