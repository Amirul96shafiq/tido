---
name: tido-domain
description: >-
  tido domain knowledge for expense receipts, invoices, labels, budgets,
  Ollama OCR, WhatsApp Evolution webhooks, and Google Drive sync. Use when
  working on Invoice/InvoiceItem/Label/Budget models, receipt parsing,
  ExtractReceiptDataJob, OllamaService, WhatsApp webhooks, Drive sync, budget
  alerts, dashboard analytics, or any MYR spending feature.
---

# tido Domain

## When to use

Read this skill before changing receipt ingestion, AI parsing, categories (labels), budgets, or spending analytics. For deeper pipeline detail see [pipeline.md](pipeline.md).

## Domain model (6 models)

| Model | Role |
|-------|------|
| `Invoice` | Receipt header: merchant, amounts, status, image, `raw_ai_response`, `receipt_hash`; `payment_method_id` |
| `InvoiceItem` | Line item → `belongsTo` Invoice + Label; optional warranty/serial |
| `Label` | Expense category (`LabelType` enum); system-seeded + user-created |
| `PaymentMethod` | Payment rail (Settings CRUD); system-seeded + user-created; aliases for OCR/WhatsApp |
| `Budget` | Cap per label/period (daily…yearly); threshold alerts |
| `User` | Filament admin; locale/timezone/notification prefs |

Money is always **MYR** (`decimal(12,2)`). Display as `RM …`.

## Invoice lifecycle

`pending` → `parsed` → `reviewed`  
Failure paths: `requires_manual_review` | `failed`  
Sources: `manual` | `whatsapp` | `google_drive`

Scopes: `processed()` = parsed|reviewed; `inPeriod($start, $end)` on `date_time`.

## Labels (not categories)

- Table/model: **labels** (`Label`)
- AI maps `suggested_category` slug → `Label` with `LabelType::Finance`
- System defaults from `LabelSeeder` (Food & Dining, Transport, etc.)

## Payment methods

- Table/model: **payment_methods** (`PaymentMethod`); Filament under Settings
- AI / WhatsApp map via `PaymentMethodMatcher` (slug, name, aliases)
- System defaults from `PaymentMethodSeeder` (Cash, Visa, Mastercard, Pay with QR, Touch 'n Go, MYKASIH, Other)

## Key classes

| Concern | Class |
|---------|-------|
| OCR HTTP | `App\Services\OllamaService` |
| Prompt JSON schema | `App\Prompts\ReceiptExtractionPrompt` |
| Manual text labels | `App\Prompts\ManualInvoiceLabelPrompt` + `ParseManualWhatsAppInvoiceJob` |
| Manual text parser | `App\Support\ManualWhatsAppInvoiceParser` |
| Parse job (vision) | `App\Jobs\ExtractReceiptDataJob` |
| Hash + dispatch + alerts | `App\Observers\InvoiceObserver` |
| WhatsApp in | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| WhatsApp out | `App\Services\WhatsAppNotificationService` |
| Drive sync | `App\Services\GoogleDriveService` + `SyncGoogleDriveJob` |
| Budget breach | `App\Services\BudgetAlertService` |
| Forecast widget | `App\Services\SpendingForecastService` |
| Matcher | `App\Services\LabelMatcher`, `App\Services\PaymentMethodMatcher` |

## Filament map

- Resources: Invoices, Budgets (Finances); Labels, Payment Methods, Family Members (Settings); EvolutionAPI (Integrations); Backups (Tools, last) — models `Label`, `PaymentMethod`, `FamilyMember`, `Backup`
- View records: always `ViewAction::make()->slideOver()` — never dedicated View pages; use the disabled form schema (no custom `infolist()` / `*Infolist.php`)
- Upload UI: `ReceiptUploadPage` → creates pending invoices
- Dashboard widgets use `DashboardMonthAnalytics` / month period helpers
- Single-line overflowing labels: `docs/ui-text-marquee.md` (Blade + Alpine; Filament Select via `SelectValueMarquee`)

- Notes fields: `NotesRichEditor` — `docs/ui-notes-rich-editor.md` (Budget `notes`, Invoice `notes`, Label `description` as Label Notes, Payment Method `notes`)
- Form empty fields: placeholders vs defaults — `docs/ui-form-empty-defaults.md`

## Config / env

- `config/services.php` → `ollama.*`, `evolution.*` (API URL/key/instance). Contact allowlist: Profile `users.phone` + Family Members with allowlist enabled (legacy `PERSONAL_WHATSAPP_*` env is seed-import only)
- `config/filesystems.php` → `google` disk
- Setup guides: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/whatsapp-manual-invoice.md`, `docs/google-drive-setup.md`

## Hard rules

1. Ollama: `"format": "json"` + strip markdown before decode
2. Webhooks: auth first, queue heavy work
3. Tests: `Http::fake` / `Queue::fake` — never real Ollama/Evolution
4. Do not reintroduce “Category” naming for expense tags
5. Architecture conflicts → warn using `docs/system-architecture.md`
