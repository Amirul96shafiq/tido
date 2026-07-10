---
name: tido-domain
description: >-
  tido domain knowledge for expense receipts, invoices, labelings, budgets,
  Ollama OCR, WhatsApp Evolution webhooks, and Google Drive sync. Use when
  working on Invoice/InvoiceItem/Labeling/Budget models, receipt parsing,
  ExtractReceiptDataJob, OllamaService, WhatsApp webhooks, Drive sync, budget
  alerts, dashboard analytics, or any MYR spending feature.
---

# tido Domain

## When to use

Read this skill before changing receipt ingestion, AI parsing, categories (labelings), budgets, or spending analytics. For deeper pipeline detail see [pipeline.md](pipeline.md).

## Domain model (5 models)

| Model | Role |
|-------|------|
| `Invoice` | Receipt header: merchant, amounts, status, image, `raw_ai_response`, `receipt_hash` |
| `InvoiceItem` | Line item → `belongsTo` Invoice + Labeling; optional warranty/serial |
| `Labeling` | Expense category (`LabelingType` enum); system-seeded + user-created |
| `Budget` | Cap per labeling/period (daily…yearly); threshold alerts |
| `User` | Filament admin; locale/timezone/notification prefs |

Money is always **MYR** (`decimal(12,2)`). Display as `RM …`.

## Invoice lifecycle

`pending` → `parsed` → `reviewed`  
Failure paths: `requires_manual_review` | `failed`  
Sources: `manual` | `whatsapp` | `google_drive`

Scopes: `processed()` = parsed|reviewed; `inPeriod($start, $end)` on `date_time`.

## Labelings (not categories)

- Table/model renamed from categories → **labelings**
- AI maps `suggested_category` slug → `Labeling` with `LabelingType::Finance`
- System defaults from `LabelingSeeder` (Food & Dining, Transport, etc.)

## Key classes

| Concern | Class |
|---------|-------|
| OCR HTTP | `App\Services\OllamaService` |
| Prompt JSON schema | `App\Prompts\ReceiptExtractionPrompt` |
| Parse job | `App\Jobs\ExtractReceiptDataJob` |
| Hash + dispatch + alerts | `App\Observers\InvoiceObserver` |
| WhatsApp in | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| WhatsApp out | `App\Services\WhatsAppNotificationService` |
| Drive sync | `App\Services\GoogleDriveService` + `SyncGoogleDriveJob` |
| Budget breach | `App\Services\BudgetAlertService` |
| Forecast widget | `App\Services\SpendingForecastService` |

## Filament map

- Resources: Invoices, Budgets (Finances); Labels (Settings) — model still `Labeling`
- Upload UI: `ReceiptUploadPage` → creates pending invoices
- Dashboard widgets use `DashboardMonthAnalytics` / month period helpers

## Config / env

- `config/services.php` → `ollama.*`, `evolution.*`, `PERSONAL_WHATSAPP_NUMBER`, `PERSONAL_WHATSAPP_EXTRA_NUMBERS`
- `config/filesystems.php` → `google` disk
- Setup guides: `docs/ollama-setup.md`, `docs/evolution-api-setup.md`, `docs/google-drive-setup.md`

## Hard rules

1. Ollama: `"format": "json"` + strip markdown before decode
2. Webhooks: auth first, queue heavy work
3. Tests: `Http::fake` / `Queue::fake` — never real Ollama/Evolution
4. Do not reintroduce “Category” naming for expense tags
5. Architecture conflicts → warn using `docs/system-architecture.md`
