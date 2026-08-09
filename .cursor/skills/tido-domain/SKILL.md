---
name: tido-domain
description: >-
  tido domain knowledge for expense receipts, expenses, labels, budgets,
  family members / household access, Ollama OCR, and WhatsApp Evolution webhooks.
  Use when working on Expense/ExpenseItem/Label/Budget/FamilyMember models,
  receipt parsing, ExtractReceiptDataJob, OllamaService, WhatsApp webhooks,
  budget alerts, dashboard analytics, household roles, or any MYR spending feature.
---

# tido Domain

## When to use

Read this skill before changing receipt ingestion, AI parsing, categories (labels), budgets, family members / household access, or spending analytics. For deeper pipeline detail see [pipeline.md](pipeline.md). Household roles, attribution, and family login: `docs/household-access.md`.

This skill covers the **Finances** module. Home also has Training / Health / Task dashboard shells (coming soon) — module tabs and how to add views: `docs/dashboard-views.md`. Do not invent non-Finances domain models here.

## Domain model (7 models)

| Model | Role |
|-------|------|
| `Expense` | Receipt header: merchant, amounts, status, image/PDF document, `raw_ai_response`, `receipt_hash`; `payment_method_id`; optional `family_member_id` (**Uploaded By**); WhatsApp message and file metadata |
| `ExpenseItem` | Line item → `belongsTo` Expense + Label; optional warranty/serial |
| `Label` | Expense category (`LabelType` enum); system-seeded + user-created |
| `PaymentMethod` | Payment rail (Settings CRUD); system-seeded + user-created; aliases for OCR/WhatsApp |
| `Budget` | Cap per label/period (daily…yearly); threshold alerts |
| `FamilyMember` | Household contact: WhatsApp allowlist + optional panel login; expenses attributed via `family_member_id` |
| `User` | Filament admin; `household_role` primary \| family_member; locale/timezone/notification prefs |

Money is always **MYR** (`decimal(12,2)`). Display as `RM …`.

## Expense lifecycle

`pending` → `parsed` → `reviewed`  
Failure paths: `requires_manual_review` | `failed`  
Sources: `manual` | `whatsapp`

Scopes: `processed()` = parsed|reviewed; `inPeriod($start, $end)` on `date_time`.

Attribution: `family_member_id` null = Primary; set from WhatsApp sender (`ExpenseSenderAttribution`) or acting family-member user. Mutate ACL: `HouseholdAccess::canMutateExpense()` / `ExpensePolicy`.

## Labels (not categories)

- Table/model: **labels** (`Label`)
- AI maps `suggested_category` slug → `Label` with `LabelType::Finance`
- System defaults from `LabelSeeder` (Food & Dining, Transport, etc.)

## Payment methods

- Table/model: **payment_methods** (`PaymentMethod`); Filament under Settings
- AI / WhatsApp map via `PaymentMethodMatcher` (slug, name, aliases)
- System defaults from `PaymentMethodSeeder` (Cash, Visa, Mastercard, Pay with QR, Touch 'n Go, MYKASIH, Other)

## Household access

- Roles: `HouseholdRole` on `User`; helpers in `HouseholdAccess`
- Primary-only pages/resources: `RequiresPrimaryHouseholdAccess`
- Family login: `login_enabled` → `FamilyMemberLoginService` + WhatsApp OTP
- Dashboard spender filter: `DashboardSpenderScope` (`all` / `primary` / `family:{id}`)
- Full map: `docs/household-access.md`

## Key classes

| Concern | Class |
|---------|-------|
| OCR HTTP | `App\Services\OllamaService` |
| PDF preparation | `App\Services\PdfPageInspector`, `App\Services\PdfPageRenderer`, `App\Services\ReceiptDocumentPreparer` |
| Prompt JSON schema | `App\Prompts\ReceiptExtractionPrompt` |
| Manual text labels | `App\Prompts\ManualExpenseLabelPrompt` + `ParseManualWhatsAppExpenseJob` |
| Manual text parser | `App\Support\ManualWhatsAppExpenseParser` |
| Parse job (vision) | `App\Jobs\ExtractReceiptDataJob` |
| Hash + dispatch + alerts | `App\Observers\ExpenseObserver` |
| WhatsApp in | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| WhatsApp LID mapping | `App\Support\WhatsAppLid` |
| WhatsApp out | `App\Services\WhatsAppNotificationService` |
| Budget breach | `App\Services\BudgetAlertService` |
| Forecast widget | `App\Services\SpendingForecastService` |
| Matcher | `App\Services\LabelMatcher`, `App\Services\PaymentMethodMatcher` |
| Family login sync | `App\Services\FamilyMemberLoginService` + `FamilyMemberObserver` |
| Household ACL | `App\Support\HouseholdAccess`, `App\Policies\ExpensePolicy` |
| Attribution / spender | `App\Support\ExpenseSenderAttribution`, `App\Support\DashboardSpenderScope` |

## Filament map

- Resources: Upload Receipts, Expenses, Budgets (Finances); Labels, Payment Methods, Family Members (Settings); Evolution API (Integrations); Backups, Service Status (Tools) — models `Label`, `PaymentMethod`, `FamilyMember`, `Backup`
- Primary-only: Budgets, Labels, Payment Methods, Family Members, Evolution, Backups (`RequiresPrimaryHouseholdAccess`); Service Status is household-readable with primary-only manual probes
- View records: always `ViewAction::make()->slideOver()` — never dedicated View pages; use the disabled form schema (no custom `infolist()` / `*Infolist.php`)
- Upload UI: `ReceiptUploadPage` → creates pending expenses (stamps `family_member_id` for family users)
- Dashboard: Finances widgets use `DashboardMonthAnalytics` / month + spender filters; Training / Health / Task are coming-soon shells — `docs/dashboard-views.md`
- Single-line overflowing labels: `docs/ui-text-marquee.md` (Blade + Alpine; Filament Select via `SelectValueMarquee`)

- Notes fields: `NotesRichEditor` — `docs/ui-notes-rich-editor.md` (Budget `notes`, Expense `notes`, Label `description` as Label Notes, Payment Method `notes`)
- Form empty fields: placeholders vs defaults — `docs/ui-form-empty-defaults.md`

## Config / env

- `config/services.php` → `ollama.*`, `documents.*`, `evolution.*` (API URL, outbound API key, distinct inbound webhook secret, instance). PDF parsing uses Poppler `pdfinfo` / `pdftocairo`; configure `PDF_MAX_BYTES`, `PDF_MAX_PAGES`, `PDFINFO_BINARY`, and `PDFTOCAIRO_BINARY`. Contact allowlist: Profile `users.phone` + Family Members with allowlist enabled; linked WhatsApp LIDs are stored on `users.whatsapp_lid` / `family_members.whatsapp_lid`.
- Family OTP local test: `WHATSAPP_LOGIN_DEV_OTP` / `WHATSAPP_LOGIN_DEV_PHONES` — `docs/household-access.md`, `docs/evolution-local-windows.md`
- Setup guides: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/whatsapp-manual-expense.md`, `docs/service-status.md`, `docs/household-access.md`

## Hard rules

1. Ollama: `"format": "json"` + strip markdown before decode
2. Webhooks: auth first, queue heavy work
3. Tests: `Http::fake` / `Queue::fake` — never real Ollama/Evolution
4. Do not reintroduce “Category” naming for expense tags
5. Architecture conflicts → warn using `docs/system-architecture.md`
6. New Settings/Tools/Integrations pages: gate with `RequiresPrimaryHouseholdAccess`
7. Expense mutate paths: respect `HouseholdAccess::canMutateExpense()`
