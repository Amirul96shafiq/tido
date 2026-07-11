# Agent Onboarding — tido

How this project works and how to change it safely. Cursor loads `.cursor/rules/*.mdc` automatically; activate skills under `.cursor/skills/` when the task matches.

## 1. What you are building

**tido** is a single-tenant personal finance app for **Malaysian Ringgit (MYR)**. It ingests receipt images, extracts structured data with a **local Ollama** vision model, categorizes line items as **Labels** (model: `Label`), tracks **Budgets**, and surfaces analytics in a **Filament v5** admin at `/admin`.

Primary ingestion paths:

| Channel | Entry | Creates |
|---------|-------|---------|
| WhatsApp | `POST /api/webhooks/whatsapp` | Pending `Invoice` |
| Google Drive | `SyncGoogleDriveJob` (every 15m) | Pending `Invoice` |
| UI upload | `ReceiptUploadPage` | Pending `Invoice` |
| Manual CRUD | `InvoiceResource` | Invoice (may still trigger observer) |

Default login (seeded): `admin@tido.local` / `password`.

## 2. Read order for new agents

1. This file
2. `.cursorrules` — hard coding/security constraints
3. `docs/system-architecture.md` — product blueprint (note: some version numbers are outdated; trust Laravel 12 / PG 17 / stack in `AGENTS.md`)
4. Domain skill: `.cursor/skills/tido-domain/SKILL.md` (+ `pipeline.md` when touching OCR/webhooks)
5. Existing skills: `laravel-best-practices`, `pest-testing`, `configuring-horizon`, `tailwindcss-development`
6. Setup ops only when needed: `docs/ollama-setup.md`, `docs/evolution-api-setup.md` (Sail/Docker) or `docs/evolution-local-windows.md` (host, no Docker), `docs/google-drive-setup.md`

Stock `README.md` is the Laravel skeleton — **not** product documentation.

## 3. Directory map

```
app/
  Models/           Invoice, InvoiceItem, Label, Budget, User
  Filament/         Resources (Schemas/Tables/Pages), Pages, Widgets, Support
  Services/         Ollama, GoogleDrive, WhatsApp, BudgetAlert, SpendingForecast
  Jobs/             ExtractReceiptDataJob, SyncGoogleDriveJob
  Observers/        InvoiceObserver
  Prompts/          ReceiptExtractionPrompt
  Enums/            LabelType, UserLocale, UserDateFormat
  Http/Controllers/Api/  WhatsAppWebhookController
routes/
  web.php           / → /admin, changelog JSON
  api.php           WhatsApp webhook
  console.php       schedules (Drive sync, backups)
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
| Money | `decimal(12,2)`, cast `decimal:2`, currency `MYR`, UI `RM` |
| Duplicate | `receipt_hash` SHA-256 of number + datetime + total |
| Statuses | `pending`, `parsed`, `reviewed`, `requires_manual_review`, `failed` |
| Auth | Filament session; no Spatie Permission; no tenancy |
| Panel | `AdminPanelProvider` only — path `admin` |

Relationships: Invoice `hasMany` InvoiceItems; InvoiceItem `belongsTo` Label; Budget `belongsTo` Label.

## 5. How to implement features

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
3. View is always a slide-over — never a dedicated View page. Tables: `ViewAction::make()->slideOver()` in `recordActions` (before Edit/Delete). Notification/deep-link View CTAs: `Resource::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $record->getRouteKey()])`
4. Record actions are icon-only panel-wide (`AppServiceProvider` → `Table::configureUsing` → `modifyUngroupedRecordActionsUsing` → `iconButton()`); do not add visible labels on View/Edit/Delete
5. Nav groups: Finances / Settings
6. Breadcrumbs are disabled panel-wide (`AdminPanelProvider` → `->breadcrumbs(false)`); do not re-enable on resources
7. Widgets: reuse `InteractsWithDashboardMonth` for month-scoped stats
8. Resource table `created_at` columns use `->since()->dateTimeTooltip()` (relative time + full datetime on hover), matching Receipt Upload “Uploaded At”

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
- Assuming multi-user isolation — app is single-tenant
- Editing architecture (new ingestion channel, schema) without checking `docs/system-architecture.md`
- Horizon `viewHorizon` gate empty allowlist — configure before relying on `/horizon` in prod

## 8. Useful commands

```bash
php artisan route:list --path=admin
php artisan route:list --path=api
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run dev          # or npm run dev:full (vite + serve:2000 + queue)
```

Sail stack (see `compose.yaml`): `laravel.test`, `pgsql`, `redis`, `ollama`, `evolution-api`.
