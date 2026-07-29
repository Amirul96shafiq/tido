# Agent Onboarding — tido

How this project works and how to change it safely. Cursor loads `.cursor/rules/*.mdc` automatically; activate skills under `.cursor/skills/` when the task matches.

## 1. What you are building

**tido** is a single-tenant **personal hub** in a **Filament v5** admin at `/admin`. The Home dashboard switches modules via icon tabs (see [`dashboard-views.md`](dashboard-views.md)):

| Dashboard view | Status |
|----------------|--------|
| **Finances** | Shipped — MYR receipts, budgets, analytics |
| **Training** | Coming soon |
| **Health** | Coming soon |
| **Task** | Coming soon |

**Finances** (shipped today) uses **Malaysian Ringgit (MYR)**. It ingests receipt **images** and WhatsApp **text manual invoices**, extracts or classifies data with a **local Ollama** model, categorizes line items as **Labels** (model: `Label`), tracks **Budgets**, and surfaces analytics. Sidebar nav group **Finances** (Upload Receipts, Invoices, Budgets) is the CRUD surface for that module — distinct from the dashboard view tabs.

Primary Finances ingestion paths:

| Channel | Entry | Creates |
|---------|-------|---------|
| WhatsApp image | `POST /api/webhooks/whatsapp` | Pending `Invoice` → vision OCR |
| WhatsApp manual text | same webhook (fixed text format) | Pending `Invoice` (no image) → label job → `requires_manual_review` |
| Google Drive | `SyncGoogleDriveJob` (every 15m) | Pending `Invoice` |
| UI upload | `ReceiptUploadPage` | Pending `Invoice` |
| Manual CRUD | `InvoiceResource` | Invoice (may still trigger observer) |

Default login (seeded): `admin@tido.local` / `password`.

## 2. Read order for new agents

1. This file
2. `.cursorrules` — hard coding/security constraints
3. `docs/system-architecture.md` — product blueprint (note: some version numbers are outdated; trust Laravel 12 / PG 17 / stack in `AGENTS.md`)
4. Dashboard modules (Finances / Training / Health / Task): `docs/dashboard-views.md`
5. Domain skill: `.cursor/skills/tido-domain/SKILL.md` (+ `pipeline.md` when touching OCR/webhooks) — Finances domain
6. Existing skills: `laravel-best-practices`, `pest-testing`, `configuring-horizon`, `tailwindcss-development`
7. Setup ops only when needed: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/whatsapp-bot-commands.md`, `docs/whatsapp-manual-invoice.md`, `docs/google-drive-setup.md`
8. UI empty panels: `docs/ui-empty-states.md`
9. Modal blur / width: `docs/ui-modal-overlay.md`
10. Vite panel assets (`Vite::asset` vs `@vite`, when to `npm run build`): `docs/vite-assets.md`
11. Sticky top/bottom bars + blur veil: `docs/ui-sticky-blur.md`
12. Sticky section tabs + smooth scroll: `docs/ui-section-nav.md` (Finances widget jump tabs — not dashboard view tabs)
13. Icon CTA tooltips (Filament Tippy, not browser `title`): `docs/ui-tooltips.md`
14. Single-line text marquee (overflow RTL scroll): `docs/ui-text-marquee.md`
15. Dark theme (Slate surfaces / tooltips / scrollbars / solid CTA text): `docs/ui-dark-theme.md`
16. UI copy voice (impersonal, no we/you): `docs/ui-copy-style.md`
17. Form draft auto-save / crash recovery: `docs/content-draft-recovery.md`
18. Notes rich editor (`notes` fields): `docs/ui-notes-rich-editor.md`
19. Resource form empty placeholders / defaults: `docs/ui-form-empty-defaults.md`
20. Custom Blade toggles (color classes + inlineLabel layout): `docs/ui-custom-toggles.md`
21. Backups catalog, restore tokens, Danger Zone: `docs/backups-and-danger-zone.md`
22. Service Status (health probes, uptime UI): `docs/service-status.md`
23. Profile Active Sessions (list, revoke, device parsing): `docs/active-sessions.md`
24. Household access (attribution, family login, invoice ACL): `docs/household-access.md`
25. Git workflow (feature branches, PRs, staging/production): `docs/git-workflow.md`

Root [`README.md`](../README.md) is the GitHub landing doc (setup, stack, usage). This file and the rest of `docs/` are the deep product and agent map.

## 3. Directory map

```
app/
  Models/           Invoice, InvoiceItem, Label, PaymentMethod, Budget, FamilyMember, User, ContentDraft, Backup, ServiceHealthSample
  Filament/         Resources (Schemas/Tables/Pages), Pages, Widgets, Concerns, Support, Livewire
  Services/         Ollama, GoogleDrive, WhatsApp, BudgetAlert, SpendingForecast, FamilyMemberLoginService, Backup*, Health/*, ActiveSessionService, AccountDangerZone, LabelMatcher, PaymentMethodMatcher
  Jobs/             ExtractReceiptDataJob, ProcessManualWhatsAppInvoiceJob, ParseManualWhatsAppInvoiceJob, SyncGoogleDriveJob, …
  Observers/        InvoiceObserver, FamilyMemberObserver
  Policies/         InvoicePolicy (household mutate ACL)
  Prompts/          ReceiptExtractionPrompt, ManualInvoiceLabelPrompt
  Support/          HouseholdAccess, DashboardSpenderScope, InvoiceSenderAttribution, ManualWhatsAppInvoiceParser, WhatsAppMessage, …
  Enums/            HouseholdRole, LabelType, UserLocale, UserDateFormat, MonitoredService, ServiceHealthStatus
  Http/Controllers/ Api webhooks, BackupDownload, GuestRestoreBackup
routes/
  web.php           / → /admin, changelog JSON, backup download / guest restore
  api.php           WhatsApp webhook
  console.php       schedules (Drive sync, backups, health:probe / health:prune)
database/
  migrations|factories|seeders
docs/               architecture + integration setup + this file
.cursor/rules/      always-on + glob-scoped agent rules
.cursor/skills/     domain and framework skills
```

## 4. Domain cheat sheet

| Concept | Truth in code |
|---------|----------------|
| Category | **`Label`** model / `labels` table (UI: **Label** / **Labels**) |
| Payment method | **`PaymentMethod`** model / `payment_methods` table (Settings CRUD; AI/WhatsApp via aliases) |
| Family member | **`FamilyMember`** model / `family_members` table (Settings CRUD; bot allowlist + optional panel login) |
| Uploaded By | Invoice `family_member_id` — null = Primary; set from WhatsApp sender or acting user — `docs/household-access.md` |
| Money | `decimal(12,2)`, cast `decimal:2`, currency `MYR`, UI `RM` |
| Duplicate | `receipt_hash` SHA-256 of number + datetime + total |
| Statuses | `pending`, `parsed`, `reviewed`, `requires_manual_review`, `failed` |
| Auth | Filament session; household roles (`HouseholdRole`); no Spatie Permission; no tenancy |
| Panel | `AdminPanelProvider` only — path `admin`; family members get limited Finances access |

Relationships: Invoice `hasMany` InvoiceItems; Invoice `belongsTo` FamilyMember (optional); InvoiceItem `belongsTo` Label; Budget `belongsTo` Label; FamilyMember `hasOne` login User.

## 5. How to implement features

### Git workflow

Before coding a feature or fix: branch from up-to-date `main` (`feature/...` or `fix/...`), open a PR into `main`, then return to `main` after merge. Do not develop features on `main`. See `docs/git-workflow.md` for multi-developer rules and future staging/production promotion.

### Backend (models, jobs, services)

1. Activate `laravel-best-practices` (+ `tido-domain` if domain-related)
2. Boost `search-docs` before using unfamiliar Laravel/Filament APIs
3. Boost `database-schema` before migrations
4. `declare(strict_types=1);`, Pint after edits
5. Put side effects in Services/Jobs/Observers — keep Filament Resources thin
6. Add/update Pest tests; mock HTTP/queues/storage

### Filament UI

1. Follow nested Resource layout: `Resources/{Plural}/{Singular}Resource.php` + `Schemas/` + `Tables/` + `Pages/`
2. Forms use Filament v5 `Schema`; prefer native components
3. View is always a slide-over — never a dedicated View page. Tables: `ViewAction::make()->slideOver()` ungrouped in `recordActions` (left of `RecordActionsGroup`). The slide-over uses the resource **form** schema in disabled mode — do **not** add `Resource::infolist()` or `Schemas/*Infolist.php`. Notification/deep-link View CTAs: `Resource::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $record->getRouteKey()])`
4. Ungrouped record actions are icon-only panel-wide (`AppServiceProvider` → `Table::configureUsing` → `modifyUngroupedRecordActionsUsing` → `iconButton()` + Filament `->tooltip()` from the action label). Resource tables put Edit/Delete/custom actions in `App\Filament\Support\RecordActionsGroup` (vertical ellipsis); Upload Receipts / Recent Receipts stay Edit-only — see `docs/ui-tooltips.md`
5. Filter and Column Manager triggers also get Tippy tooltips globally via `filtersTriggerAction` / `columnManagerTriggerAction` in `AppServiceProvider`
6. List-page “New …” CTAs use a plus Heroicon panel-wide (`AppServiceProvider` → `CreateAction::configureUsing` → `->icon(Heroicon::Plus)`); new List pages only need `CreateAction::make()`
7. Edit pages: use `App\Filament\Concerns\AppendsResourceLabelToEditTitle` so the title ends with the singular model label (see `.cursor/rules/filament-conventions.mdc` — Edit page title)
8. Nav groups: Finances (Upload Receipts, Invoices, Budgets) / Settings (Labels, Payment Methods, Family Members) / Integrations (Evolution API) / Tools (Backups, Service Status) — Tools last. Home dashboard modules (Finances / Training / Health / Task): `docs/dashboard-views.md` (not sidebar groups)
9. Breadcrumbs use Filament native defaults plus `App\Filament\Concerns\PrependsHomeBreadcrumb` (Home → resource → page). Do not disable panel-wide or add a custom “Go back to table” header. New pages must use the trait; Create/Edit pages also register in the `PAGE_END` draft-poller scopes.
10. Widgets: reuse `InteractsWithDashboardMonth` for month-scoped stats
11. Resource table `created_at` columns use `->since()->dateTimeTooltip()` (relative time + full datetime on hover), matching Receipt Upload “Uploaded At”
12. Illustrated empty panels: Filament **tables** use `emptyStateHeading` / `Description` / `Icon` / optional `Actions` (see `docs/ui-empty-states.md` — Filament tables section); custom Blade / filtered drawers use `<x-empty-state-panel>` (pattern from `errors/email-change-expired.blade.php`)
13. Custom Alpine / Blade icon CTAs: use `x-tooltip` + `theme: $store.theme` (never bare `title=`). High-z custom modals (restore backup at `z-index: 99999`) must set Tippy `zIndex: 100000` — see `docs/ui-tooltips.md`
14. Dark theme surfaces: Slate with slate-800 chrome — see `docs/ui-dark-theme.md` (do not reintroduce Zinc / `#333` tooltips, or white text on solid gold CTAs)
15. UI copy: impersonal voice — no *we* / *you* / *your* in headings, descriptions, notifications; see `docs/ui-copy-style.md`
16. Backups / Danger Zone / guest restore: see `docs/backups-and-danger-zone.md` — do not invent a second restore path
17. Service Status / health probes: see `docs/service-status.md`
18. Profile Active Sessions (embedded table, revoke): see `docs/active-sessions.md`
19. Household access / family login / invoice ACL: see `docs/household-access.md`
20. Sticky section tabs + smooth scroll: see `docs/ui-section-nav.md`
21. Resource form empty fields: placeholders vs defaults — see `docs/ui-form-empty-defaults.md` when adding or extending `*Form.php` schemas
22. Custom Blade toggles: use `get_component_color_classes(ToggleComponent::class, …)` and Profile `inlineLabel` markup — see `docs/ui-custom-toggles.md`

### Integrations

1. Ollama: always `format: json` + strip markdown fences (see `OllamaService`)
2. Webhooks: Bearer auth → validate → queue
3. Never call real Ollama/Evolution in tests

### After code changes

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=YourTest
```

## 6. Cursor rules index

| Rule file | Applies |
|-----------|---------|
| `project-overview.mdc` | Always — identity & entry points |
| `php-conventions.mdc` | `app/`, `database/`, `routes/`, `tests/` PHP |
| `filament-conventions.mdc` | `app/Filament/`, Filament views |
| `receipt-pipeline.mdc` | Services, Jobs, Observers, API webhooks, Prompts |
| `testing-conventions.mdc` | `tests/` |

## 7. Common pitfalls

- Calling categories “Category” in new code — use **Label** / **Labels**
- Hitting live Ollama in Pest — use `Http::fake()`
- Forgetting `InvoiceObserver` side effects when creating invoices in tests — use `Queue::fake()` or `unsetEventDispatcher()` when appropriate
- Assuming multi-tenancy or Spatie roles — single household; use `HouseholdAccess` / `HouseholdRole` — see `docs/household-access.md`
- Letting family members mutate invoices they did not upload — gate with `HouseholdAccess::canMutateInvoice()` / `InvoicePolicy`
- Editing architecture (new ingestion channel, schema) without checking `docs/system-architecture.md`
- Horizon `viewHorizon` gate empty allowlist — configure before relying on `/horizon` in prod
- Using browser `title=` on icon CTAs instead of Filament Tippy — see `docs/ui-tooltips.md`
- Inventing a second backup/restore path outside `BackupService` — see `docs/backups-and-danger-zone.md`
- Adding a new `Vite::asset()` panel script without `npm run build` — stale manifest can crash `dev:full` / `dev:all`; see `docs/vite-assets.md`

## 8. Useful commands

```bash
php artisan route:list --path=admin
php artisan route:list --path=api
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run dev          # or npm run dev:full / dev:all (vite + serve:2000 + queue; does not run build)
npm run build        # once after new Vite::asset() entry paths — see docs/vite-assets.md
```

Local stack: native Ollama (`docs/ollama-setup.md`, `OLLAMA_HOST=http://127.0.0.1:11434`) and Evolution (`docs/evolution-local-windows.md`) on the Windows host with `npm run dev:full`.
