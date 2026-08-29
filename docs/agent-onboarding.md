# Agent Onboarding — tido

How this project works and how to change it safely.

- **Codex**: loads root `AGENTS.md` automatically; follows `.codex/CODEX_WORKFLOW.md` and `.codex/VERIFICATION.md`; project MCP configuration lives in `.codex/config.toml`
- **Cursor IDE**: loads `.cursor/rules/*.mdc` automatically; activate skills under `.cursor/skills/`
- **Antigravity IDE**: loads `.agents/AGENTS.md` automatically; activate skills under `.agents/skills/`

Activate the relevant skill when the task matches your domain.

## 1. What you are building

**tido** is a single-tenant **personal hub** in a **Filament v5** admin at `/admin`. The Home dashboard switches modules via icon tabs (see [`dashboard-views.md`](dashboard-views.md)):

| Dashboard view | Status                                     |
| -------------- | ------------------------------------------ |
| **Finances**   | Shipped — MYR receipts, budgets, analytics |
| **Training**   | Coming soon                                |
| **Health**     | Coming soon                                |
| **Task**       | Coming soon                                |

**Finances** (shipped today) uses **Malaysian Ringgit (MYR)** for canonical reporting. It ingests receipt **images**, WhatsApp **PDF documents**, and WhatsApp **text manual expenses**, detects printed source currency with a **local Ollama** model, converts foreign receipt amounts using a date-specific exchange-rate provider, categorizes line items as **Labels** (model: `Label`), tracks **Budgets** and **Recurrings**, and surfaces analytics. Sidebar nav group **Finances** (Upload Receipts, Expenses, Budgets, Recurrings) is the CRUD surface for that module — distinct from the dashboard view tabs.

Primary Finances ingestion paths:

| Channel              | Entry                            | Creates                                                                               |
| -------------------- | -------------------------------- | ------------------------------------------------------------------------------------- |
| WhatsApp image       | `POST /api/webhooks/whatsapp`    | Pending `Expense` → vision OCR                                                        |
| WhatsApp PDF         | same webhook (`application/pdf`) | Validated/stored pending `Expense` → Poppler page rendering → page extraction + merge |
| WhatsApp manual text | same webhook (fixed text format) | Pending `Expense` (no image) → label job → `requires_manual_review`                   |
| UI upload            | `ReceiptUploadPage`              | Pending `Expense`                                                                     |
| Manual CRUD          | `ExpenseResource`                | Expense (may still trigger observer)                                                  |

Default login (seeded): `admin@tido.local` / `password`.

## 2. Read order for new agents

For authentication, sessions, webhooks, uploads, backups, signed downloads, Horizon, public endpoints, dependency advisories, or production-release work, also read [security-audit.md](security-audit.md) and [security-hardening-playbook.md](security-hardening-playbook.md). Select one `SEC-*` item before implementation and keep the change, tests, and ledger update limited to that item unless an unavoidable prerequisite is recorded.

1. This file
2. Active agent workflow: root `AGENTS.md` + `.codex/CODEX_WORKFLOW.md` (Codex), `.cursorrules` (Cursor), or `.agents/AGENTS.md` (Antigravity)
3. `docs/system-architecture.md` — product blueprint (note: some version numbers are outdated; trust Laravel 12 / PG 17 / stack in `AGENTS.md`)
4. Future SaaS only (do not implement yet): `docs/saas-prd.md` — household isolation between signups; live contract remains single-tenant until architecture is updated
5. Dashboard modules (Finances / Training / Health / Task): `docs/dashboard-views.md`
6. Domain skill: activate the `tido-domain` skill surfaced by the active agent (+ its `pipeline.md` when touching OCR/webhooks) — Finances domain
7. Framework skills surfaced by the active agent: `laravel-best-practices`, `pest-testing`, `configuring-horizon`, `tailwindcss-development`
8. Setup ops only when needed: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/realtime-broadcasting.md`, `docs/whatsapp-bot-commands.md`, `docs/whatsapp-manual-expense.md`
9. UI empty panels: `docs/ui-empty-states.md`
10. Modal blur / width: `docs/ui-modal-overlay.md`
11. Vite panel assets (`Vite::asset` vs `@vite`, when to `npm run build`): `docs/vite-assets.md`
12. Sticky top/bottom bars + blur veil: `docs/ui-sticky-blur.md`
13. Sticky section tabs + smooth scroll: `docs/ui-section-nav.md` (Finances widget jump tabs — not dashboard view tabs)
14. Icon CTA tooltips (Filament Tippy, not browser `title`): `docs/ui-tooltips.md`
15. Single-line text marquee (overflow RTL scroll): `docs/ui-text-marquee.md`
16. Dark theme (Slate surfaces / 1px borders without elevation shadows / tooltips / scrollbars / solid CTA text): `docs/ui-dark-theme.md`
17. UI copy voice (impersonal, no we/you): `docs/ui-copy-style.md`
18. UI text headings (Title Case: `Text Heading`, never `Text heading`): `docs/ui-text-heading.md`
19. Count Up numeric values (stats, widgets, tables): `docs/ui-count-up.md`
20. Reduce Motion accessibility preference: `docs/ui-reduce-motion.md`
21. Form draft auto-save / crash recovery: `docs/content-draft-recovery.md`
22. Notes rich editor (`notes` fields): `docs/ui-notes-rich-editor.md`
23. Field character limits (`current/max` counters): `docs/ui-field-character-limits.md`
24. Resource form empty placeholders / defaults: `docs/ui-form-empty-defaults.md` (includes JS date pickers + `DateOfBirthPicker`)
25. Custom Blade toggles (color classes + inlineLabel layout): `docs/ui-custom-toggles.md`
26. Resource edit audit (latest editor, username display, table recency): `docs/resource-edit-audit.md`
27. Backups catalog, restore tokens, Danger Zone: `docs/backups-and-danger-zone.md`
28. Local sandbox (port 2001) for backup/wipe browser tests: `docs/sandbox-testing.md`
29. Service Status (health probes, uptime UI): `docs/service-status.md`
30. Profile Active Sessions (list, revoke, device parsing): `docs/active-sessions.md`
31. Household access (attribution, family login, expense ACL): `docs/household-access.md`
32. Git workflow (feature branches, PRs, staging/production): `docs/git-workflow.md`
33. Integration pages (Ollama / Evolution API page structure, conventions, new-integration checklist): `docs/integration-pages.md`

Root [`README.md`](../README.md) is the GitHub landing doc (setup, stack, usage). This file and the rest of `docs/` are the deep product and agent map.

## 3. Directory map

```
app/
  Models/           Expense, ExpenseItem, Label, PaymentMethod, Budget, Recurring, RecurringOccurrence, FamilyMember, User, ContentDraft, Backup, ServiceHealthSample; Concerns/TracksResourceEdits.php
  Filament/         Resources (Schemas/Tables/Pages), Pages, Widgets, Concerns, Support, Livewire
  Services/         Ollama, Currency exchange/conversion, WhatsApp, PdfPageInspector, PdfPageRenderer, ReceiptDocumentPreparer, BudgetAlert, RecurringOccurrenceGenerator, RecurringMatchService, RecurringReminderService, SpendingForecast, FamilyMemberLoginService, Backup*, Health/*, ActiveSessionService, AccountDangerZone, LabelMatcher, PaymentMethodMatcher
  Jobs/             ExtractReceiptDataJob, ProcessWhatsAppMediaJob, ProcessManualWhatsAppExpenseJob, ParseManualWhatsAppExpenseJob, …
  Observers/        ExpenseObserver, FamilyMemberObserver
  Events/           ExpenseUpdated (Reverb broadcast; id + status only)
  Policies/         ExpensePolicy (household mutate ACL)
  Prompts/          ReceiptExtractionPrompt, PdfReceiptPagePrompt, PdfReceiptMergePrompt, ManualExpenseLabelPrompt
  Support/          HouseholdAccess, DashboardSpenderScope, ExpenseSenderAttribution, ManualWhatsAppExpenseParser, WhatsAppLid, WhatsAppMessage, …
  Enums/            HouseholdRole, LabelType, RecurringType, RecurringFrequency, RecurringOccurrenceStatus, UserLocale, UserDateFormat, MonitoredService, ServiceHealthStatus
  Http/Controllers/ Api webhooks, BackupDownload, GuestRestoreBackup
routes/
  web.php           / → /admin, changelog JSON, backup download / guest restore
  api.php           WhatsApp webhook
  console.php       schedules (backups, health:probe / health:prune)
  channels.php      private Reverb channels (`household.expenses`, `App.Models.User.{id}`)
database/
  migrations|factories|seeders
docs/               architecture + integration setup + this file
.codex/              Codex workflow, verification matrix, plan template, ignored task plans, and project MCP config
.cursor/rules/      always-on + glob-scoped agent rules
.cursor/skills/     domain and framework skills
```

## 4. Domain cheat sheet

| Concept           | Truth in code                                                                                                                                                                                                                |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Expense           | **`Expense`** model / `expenses` table — spending record (Filament **Expenses** CRUD)                                                                                                                                        |
| Expense item      | **`ExpenseItem`** / `expense_items` — line items; FK `expense_id`                                                                                                                                                            |
| Receipt           | Uploaded document / ingestion vocabulary (`ReceiptUploadPage`, `receipt_hash`, OCR pipeline) — not the model name                                                                                                            |
| `invoice_number`  | Printed bill/invoice # from OCR (column + UI label **Invoice number**) — not the record PK                                                                                                                                   |
| Category          | **`Label`** model / `labels` table (UI: **Label** / **Labels**)                                                                                                                                                              |
| Payment method    | **`PaymentMethod`** model / `payment_methods` table (Settings CRUD; AI/WhatsApp via aliases)                                                                                                                                 |
| Family member     | **`FamilyMember`** model / `family_members` table (Settings CRUD; bot allowlist + optional panel login)                                                                                                                      |
| Uploaded By       | Expense `family_member_id` — null = Primary; set from WhatsApp sender or acting user — `docs/household-access.md`                                                                                                            |
| WhatsApp identity | Classic phone JIDs resolve by phone; `@lid` identities resolve through `whatsapp_lid` after primary linking in Evolution API                                                                                                 |
| Receipt document  | `image_path` stores the original image/PDF; `file_mime_type`, `file_page_count`, `original_filename`, and unique `whatsapp_message_id` preserve media metadata                                                               |
| Money             | Canonical reporting values are `decimal(12,2)` in `MYR`, cast `decimal:2`, UI `RM`; foreign source currency, original total, rate, effective date, provider, fetch time, and conversion status remain auditable on `Expense` |
| Duplicate         | `receipt_hash` SHA-256 of number + datetime + total                                                                                                                                                                          |
| Statuses          | `pending`, `parsed`, `reviewed`, `requires_manual_review`, `failed`                                                                                                                                                          |
| Auth              | Filament session; household roles (`HouseholdRole`); no Spatie Permission; no tenancy                                                                                                                                        |
| Panel             | `AdminPanelProvider` only — path `admin`; family members get limited Finances access                                                                                                                                         |

Relationships: Expense `hasMany` ExpenseItems; Expense `belongsTo` FamilyMember (optional); ExpenseItem `belongsTo` Label; Budget `belongsTo` Label; Budget `belongsTo` FamilyMember (optional owner; `null` = Primary); Budget `is_shared` spending pool; Recurring `hasMany` RecurringOccurrence; Recurring ownership mirrors Budget (`family_member_id`, `is_shared`); RecurringOccurrence `belongsTo` Expense when completed; FamilyMember `hasMany` Budgets; FamilyMember `hasOne` login User.

Recurrings (reminder-first bills/subscriptions/transfers): [recurrings.md](recurrings.md).

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
7. Edit pages: use `App\Filament\Concerns\AppendsResourceLabelToEditTitle` so the title ends with the singular model label (see the Filament conventions surfaced by the active agent)
8. Nav groups: Finances (Upload Receipts, Expenses, Budgets, Recurrings) / Settings (Labels, Payment Methods, Family Members) / Integrations (WhatsApp, AI Parsing Engine) / Tools (Backups, Service Status) — Tools last. Home dashboard modules (Finances / Training / Health / Task): `docs/dashboard-views.md` (not sidebar groups)
9. Breadcrumbs use Filament native defaults plus `App\Filament\Concerns\PrependsHomeBreadcrumb` (Home → resource → page). Do not disable panel-wide or add a custom “Go back to table” header. New pages must use the trait; Create/Edit pages also register in the `PAGE_END` draft-poller scopes.
10. Widgets: reuse `InteractsWithDashboardMonth` for month-scoped stats; lazy dashboard widgets must use `HasDashboardWidgetPlaceholder` so the centered accessible spinner remains visible while Livewire hydrates them
11. Resource tables use `updated_at` for **Edited At** (`->since()->dateTimeTooltip()` with relative time + full datetime on hover), default newest-first ordering, and **Edited By** for the latest authenticated editor (`display_name` → `name` fallback). See `docs/resource-edit-audit.md`.
12. Illustrated empty panels: Filament **tables** use `emptyStateHeading` / `Description` / `Icon` / optional `Actions` (see `docs/ui-empty-states.md` — Filament tables section); custom Blade / filtered drawers use `<x-empty-state-panel>`
13. Custom Alpine / Blade icon CTAs: use `x-tooltip` + `theme: $store.theme` (never bare `title=`). High-z custom shells at `z-index: 99999` must set Tippy `zIndex: 100000` — see `docs/ui-tooltips.md`. Native `<x-filament::modal>` (changelog, guest restore) does not.
14. Dark theme surfaces: Slate with slate-800 chrome — see `docs/ui-dark-theme.md` (do not reintroduce Zinc / `#333` tooltips, white text on solid gold CTAs, or elevation drop shadows as panel borders)
15. UI copy: impersonal voice — no _we_ / _you_ / _your_ in headings, descriptions, notifications; see `docs/ui-copy-style.md`
16. UI text headings: Title Case every word (`Text Heading`, not `Text heading` or `text heading`); see `docs/ui-text-heading.md`
17. Resource edit audit (`edited_by`, **Edited By**, **Edited At**): see `docs/resource-edit-audit.md`
18. Backups / Danger Zone / guest restore: see `docs/backups-and-danger-zone.md` — do not invent a second restore path
19. Service Status / health probes: see `docs/service-status.md`
20. Profile Active Sessions (embedded table, revoke): see `docs/active-sessions.md`
21. Household access / family login / expense ACL: see `docs/household-access.md`
22. Sticky section tabs + smooth scroll: see `docs/ui-section-nav.md`
23. Resource form empty fields: placeholders vs defaults — see `docs/ui-form-empty-defaults.md` when adding or extending `*Form.php` schemas (date fields use JS pickers / `DateOfBirthPicker`; do not reintroduce masked DOB text inputs)
24. Custom Blade toggles: use `get_component_color_classes(ToggleComponent::class, …)` and Profile `inlineLabel` markup — see `docs/ui-custom-toggles.md`

### Integrations

1. Ollama: always `format: json` + strip markdown fences (see `OllamaService`)
2. PDF receipts: validate the detected MIME type, enforce `PDF_MAX_BYTES` / `PDF_MAX_PAGES`, extract embedded text with configured Poppler `pdftotext` when available, and render pages with configured Poppler `pdfinfo` / `pdftocairo` binaries before AI extraction
3. Webhooks: authenticate `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>` before payload handling, then resolve phone or linked WhatsApp LID → validate → queue; keep the inbound secret distinct from outbound `EVOLUTION_API_KEY`
4. Foreign receipt conversion uses the configured `CURRENCY_API_*` provider with the receipt date, bounded timeout/retry, and a cached source/target/date lookup; never revalue an already converted expense automatically
5. Never call real Ollama, Evolution, or exchange-rate providers in tests
6. Integration page structure (class anatomy, section layout, setup wizard, status conventions, new-integration checklist): `docs/integration-pages.md`
7. Google OAuth Primary sign-in: `docs/google-oauth-setup.md`

### After code changes

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=YourTest
```

## 6. Agent rules index

### Codex (`AGENTS.md` + `.codex/`)

| File                       | Content                                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------- |
| `AGENTS.md`                | Always-loaded mode contract, authority boundaries, sources of truth, and non-negotiable gates   |
| `.codex/CODEX_WORKFLOW.md` | Ask / Plan / Agent / Debug lifecycle, branch discipline, debugging stages, and handoff contract |
| `.codex/VERIFICATION.md`   | Change-type verification matrix and asynchronous integration completion standard                |
| `.codex/PLAN_TEMPLATE.md`  | Template for ignored, task-local Plan-mode documents under `.codex/plans/`                      |
| `.codex/config.toml`       | Project-scoped Codex configuration, including Laravel Boost MCP                                 |

### Cursor IDE (`.cursor/rules/*.mdc`)

| Rule file                  | Applies                                          |
| -------------------------- | ------------------------------------------------ |
| `project-overview.mdc`     | Always — identity & entry points                 |
| `php-conventions.mdc`      | `app/`, `database/`, `routes/`, `tests/` PHP     |
| `filament-conventions.mdc` | `app/Filament/`, Filament views                  |
| `receipt-pipeline.mdc`     | Services, Jobs, Observers, API webhooks, Prompts |
| `testing-conventions.mdc`  | `tests/`                                         |
| `ask-before-git.mdc`       | Always — git push/commit approval                |

### Antigravity IDE (`.agents/`)

| File                                | Content                                                                          |
| ----------------------------------- | -------------------------------------------------------------------------------- |
| `AGENTS.md`                         | All rules consolidated (project overview, PHP, Filament, pipeline, testing, git) |
| `skills/architecture-guard/`        | Architecture gatekeeper                                                          |
| `skills/filament-reviewer/`         | Filament convention checker                                                      |
| `skills/security-reviewer/`         | Security audit                                                                   |
| `skills/integration-ops/`           | Ollama / Evolution / Drive / Horizon ops                                         |
| `skills/receipt-pipeline-debugger/` | Receipt pipeline debugging                                                       |
| `skills/tido-domain/`               | Finances domain knowledge                                                        |
| `skills/laravel-best-practices/`    | Laravel patterns                                                                 |
| `skills/pest-testing/`              | Pest test patterns                                                               |
| `skills/configuring-horizon/`       | Horizon setup                                                                    |
| `skills/tailwindcss-development/`   | Tailwind conventions                                                             |

## 7. Common pitfalls

- Calling categories “Category” in new code — use **Label** / **Labels**
- Hitting live Ollama or Reverb in Pest — use `Http::fake()` / `Event::fake()`; phpunit sets `BROADCAST_CONNECTION=null`
- Forgetting `ExpenseObserver` side effects when creating expenses in tests — use `Queue::fake()` or `unsetEventDispatcher()` when appropriate
- Assuming multi-tenancy or Spatie roles — single household; use `HouseholdAccess` / `HouseholdRole` — see `docs/household-access.md`
- Letting family members mutate expenses, budgets, or recurrings they do not own — gate with `HouseholdAccess::canMutateExpense()` / `canMutateBudget()` / `canMutateRecurring()` and the matching policies
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
npm run dev          # or npm run dev:full / dev:all (vite + serve:2000 + queue + reverb:8081; does not run build)
npm run build        # once after new Vite::asset() entry paths — see docs/vite-assets.md
```

Local stack: native Ollama (`docs/ollama-setup.md`, `OLLAMA_HOST=http://127.0.0.1:11434`), Evolution (`docs/evolution-local-windows.md`, `:8080`), and Reverb (`docs/realtime-broadcasting.md`, `:8081`) on the Windows host with `npm run dev:full`.
