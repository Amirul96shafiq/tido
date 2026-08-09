# Resource edit audit

Supported resource records keep the user responsible for their latest create or update. The audit metadata answers who last changed a resource while preserving the existing household attribution rules for expenses.

## Scope

The audit applies to these Eloquent models and Filament resources:

| Model | Table | Admin resource |
|-------|-------|----------------|
| `Backup` | `backups` | Tools → Backups |
| `Budget` | `budgets` | Finances → Budgets |
| `FamilyMember` | `family_members` | Settings → Family Members |
| `Expense` | `expenses` | Finances → Expenses |
| `Label` | `labels` | Settings → Labels |
| `PaymentMethod` | `payment_methods` | Settings → Payment Methods |

The shared behavior lives in [`TracksResourceEdits`](../app/Models/Concerns/TracksResourceEdits.php). The `edited_by` foreign key is added by [`add_edited_by_to_resource_tables`](../database/migrations/2026_08_03_224656_add_edited_by_to_resource_tables.php).

## Persistence behavior

- Creating or updating a supported model stamps the currently authenticated `User` ID in `edited_by`.
- Updates made without an authenticated user are treated as system changes and set `edited_by` to `null`.
- The foreign key is nullable and uses `nullOnDelete()`, so deleting a user preserves the resource and removes only the editor reference.
- `updated_at` is the authoritative **Edited At** value. Resource lists must use it for date display, date filtering, and the default newest-first sort rather than `created_at`.

This audit identifies the latest editor only; it is not a full historical revision log. Use the existing model state and activity-log integrations when a complete change history is required.

## Table display

Each supported resource table exposes:

- **Edited By** — the editor’s `users.display_name`, falling back to `users.name` when no display name is set; `System` is shown when no editor is recorded.
- **Edited At** — relative time from `updated_at` with the full date and time available through the datetime tooltip.
- **Default ordering** — newest `updated_at` records first.

The expense table retains its existing **Uploaded By** column. **Uploaded By** describes the household spender/source, while **Edited By** describes the user who last changed the record; these values must not be conflated.

## Household semantics

The editor is the authenticated account, whether that account is the Primary user or a linked Family Member. Expense `family_member_id` remains the source-of-record for household spender attribution and expense mutation authorization. The edit audit does not grant additional access: policies and `HouseholdAccess` continue to control which records a user may mutate.

Primary users can use the user-menu **Swap Account** control to sign in as an eligible linked Family Member. Edits made during that switched session are attributed to the linked Family Member and remain subject to Family Member authorization; see [household-access.md](household-access.md) for the switching workflow.

The application’s username presentation is `display_name` with a `name` fallback. Do not introduce a separate username column or display an email address in **Edited By** unless the product requirement changes.

## Verification

The persistence behavior is covered by [`ResourceEditAuditTest`](../tests/Feature/ResourceEditAuditTest.php). Resource table display and username fallback coverage belongs in [`FilamentResourceTest`](../tests/Feature/FilamentResourceTest.php), with resource-specific date/filter assertions kept beside their resource tests.

## Agent rules

1. Add new supported resource models to the shared edit-tracking concern and migration deliberately; do not duplicate `creating` / `updating` listeners in individual models.
2. Keep **Edited By** relationship-backed so table columns can eager-load the editor; format the visible username with the `display_name` → `name` fallback.
3. Use `updated_at` for resource recency. `created_at` remains appropriate for immutable creation history and ingestion-specific timestamps such as expense purchase or upload time.
4. Keep editor attribution separate from expense `family_member_id`, WhatsApp sender attribution, and `uploaded_by` presentation.
5. Cover Primary, Family Member, and unauthenticated/system create or update paths when changing the audit behavior.
