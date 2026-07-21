# Content Draft Recovery (Filament Create / Edit)

Auto-save form drafts to the database and offer crash recovery on remount. Pattern based on [Filament Daily: Auto-Save Draft and Recover Content](https://youtu.be/AaNqgPNuDb4).

## Behaviour

1. Every **10s**, `wire:poll` calls `saveDraft()` on the Livewire page.
2. Form state (`$this->data`) is stored as JSON in `content_drafts`, keyed by `user_id` + page key.
3. Empty / unchanged forms (same as post-fill reference snapshot) do **not** keep a draft.
4. On remount, if a differing draft exists → persistent notification: **Restore** / **Discard**.
5. Successful **create** or **save** deletes the draft for that key.
6. File upload fields (default: `image_path`) are excluded — Livewire temp files do not survive a crash.

## Pieces

| Piece | Path |
|-------|------|
| Table / model | `content_drafts` / `App\Models\ContentDraft` |
| Trait | `App\Filament\Concerns\RecoversContentDraft` |
| Poller UI | `resources/views/filament/hooks/content-draft-poller.blade.php` |
| Hook registration | `AdminPanelProvider` → `PanelsRenderHook::PAGE_END` (scoped to opt-in pages) |
| Tests | `tests/Feature/ContentDraftRecoveryTest.php` |

## Current opt-in pages

| Page | Draft key |
|------|-----------|
| `CreateInvoice` | `invoice-create` |
| `EditInvoice` | `invoice-edit-{id}` |
| `CreateLabel` | `label-create` |
| `EditLabel` | `label-edit-{id}` |
| `CreateBudget` | `budget-create` |
| `EditBudget` | `budget-edit-{id}` |

## Opt in a new Create / Edit page

1. Use the trait and implement the key:

```php
use App\Filament\Concerns\RecoversContentDraft;

class CreateThing extends CreateRecord
{
    use RecoversContentDraft;

    protected function contentDraftKey(): string
    {
        return 'thing-create';
    }
}

class EditThing extends EditRecord
{
    use RecoversContentDraft;

    protected function contentDraftKey(): string
    {
        return 'thing-edit-'.$this->getRecord()->getKey();
    }
}
```

2. Register the page classes in the `PAGE_END` render-hook `scopes` array in `AdminPanelProvider` (same list as Invoices / Labels / Budgets).

3. Override when needed:
   - `contentDraftExcludedFields()` — skip non-recoverable fields (uploads, secrets).
   - Do **not** redefine `afterFill` / `afterCreate` / `afterSave` without calling the trait behaviour (or call `$this->captureContentDraftReferenceState()` / `$this->clearContentDraft()` / `$this->offerContentDraftRecovery()` yourself).

4. Add Pest coverage in `ContentDraftRecoveryTest` (or a sibling file): dirty save, unchanged edit skip, restore/discard on create.

## Keys

- Prefer intent strings (`{resource}-create`, `{resource}-edit-{id}`), **not** FKs to the domain row.
- Create pages have no record yet; one draft row per user per key (`unique(user_id, key)`).

## UI

The poller badge sits at the **top-start** of the content area (below the topbar, past the sidebar) so it does not overlap the go-to-bottom CTA. It shows a pulsing amber dot + “Draft saved at …” after the first successful `saveDraft`, with a top entrance transition. It is non-interactive (`pointer-events-none`).

## Notes / rich editor fields

When a draft includes a `NotesRichEditor` field, Livewire payload state is TipTap **document JSON**, while create/save dehydrates to **HTML** in the database. See [ui-notes-rich-editor.md](ui-notes-rich-editor.md).

## Out of scope

- Custom Filament pages without Create/Edit record (`ReceiptUploadPage`, WhatsApp connection, profile) — opt in separately if needed.
- Client-only `localStorage` drafts — this feature is DB-backed so recovery works across devices/sessions for the same user.
