# System Architecture: Personal Hub (Finances + Planned Modules)

> **Agents:** Start with [agent-onboarding.md](agent-onboarding.md). Codex uses root `AGENTS.md` plus `.codex/`; Cursor uses `.cursorrules` and `.cursor/`; Antigravity uses `.agents/AGENTS.md`. Use the domain skill surfaced by the active agent.
> **Stack note:** Runtime is **Laravel 12**, **PostgreSQL 17**, Filament v5, Livewire 4 (see `AGENTS.md`). Prefer those versions if this blueprint lists older ones.  
> **Dashboard modules:** [dashboard-views.md](dashboard-views.md) — Finances shipped; Training / Health / Task coming soon.

## Quick Summary
This document defines the architectural blueprint for **tido**, a localized single-tenant personal hub. The **Finances** module is a highly automated expense tracking system: ingest, parse, and analyze financial receipts with zero manual data entry. Planned dashboard modules (**Training**, **Health**, **Task**) are placeholders today — see [dashboard-views.md](dashboard-views.md). The stack utilizes Laravel for robust API and queue management, FilamentPHP v5 for rapid dashboard generation, and localized AI models (Ollama) for zero-cost, private OCR data extraction.

**Live contract:** one household per install — no public registration, no Free/Pro plans, no multi-household tenancy. A future SaaS product (many households on one deploy, Free/Pro TBD) is described only in [saas-prd.md](saas-prd.md). Do **not** implement tenancy, Register, or billing until this architecture document is explicitly updated for that phase.

---

## 1. Core Technology Stack

| Component | Technology | Purpose |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 12 | API routing, ORM, queued jobs, and task scheduling. Must follow PSR-12 coding standards. |
| **Admin Panel / UI** | FilamentPHP v5 | Auto-generation of data tables, upload widgets, and analytical dashboards. Built on Livewire v4. |
| **AI Parsing Engine** | Ollama (Local) | Zero-cost execution of vision models (e.g., LLaVA, MiniCPM-V) for OCR and data extraction. |
| **Exchange Rates** | CurrencyAPI (configured external provider) | Historical source-currency to MYR rates for receipt-date conversion; cached and persisted with audit metadata. |
| **Messaging API** | Evolution API | Headless receipt ingestion and system alert broadcasting via WhatsApp. |
| **Database** | PostgreSQL 17 | Relational data storage optimized for JSON operations and strict indexing. |

---

## 2. UI/UX Aesthetic Guidelines (tido Filament Theme)

The interface uses FilamentPHP's native Tailwind CSS theming engine with Outfit typography, warm amber/gold brand accents, restrained light surfaces, and a Slate dark-surface system. Detailed, implementation-backed color rules live in [`ui-dark-theme.md`](ui-dark-theme.md).

### 2.1. Theme Configuration
* **Typography:** `Outfit`, configured by `AdminPanelProvider`, with the application font stack as fallback.
* **Color Palette:**
    * **Primary Accent:** Filament's configured amber/gold brand palette for primary actions and active states.
    * **Light Mode:** High whitespace, restrained borders, and subtle surface separation.
    * **Dark Mode:** `Color::Slate` with shades 900 and 950 remapped to Slate 800 so panel chrome, widgets, sections, and tables share the same lighter dark surface. Do not reintroduce Zinc surfaces or generic `#333` tooltip backgrounds.

### 2.2. Filament Panel Adjustments
* **Navigation:** Configure `->sidebarCollapsibleOnDesktop()` in the Panel Provider to maximize horizontal workspace.
* **Data Tables:** Implement borderless table designs. Use minimalist pagination and hide complex filter menus behind single icon buttons. Resource row actions: ungrouped **View** icon + vertical-ellipsis `RecordActionsGroup` for Edit/Delete/custom actions (Tippy tooltip `Actions`). Ungrouped icons use global `Table::configureUsing` → `modifyUngroupedRecordActionsUsing` → `iconButton()` **plus Filament Tippy `->tooltip()` from the action label** (also applied to Filter and Column Manager triggers). List-page “New …” CTAs use a plus Heroicon via global `CreateAction::configureUsing` → `->icon(Heroicon::Plus)`. Supported resource tables expose **Edited By** and **Edited At**, sort newest `updated_at` first, and use the username `display_name` → `name` fallback. Do not use browser `title` attributes for icon CTAs — see `docs/ui-tooltips.md`.

---

## 3. Modular Dashboard Views

Home (`Dashboard`) switches top-level modules via icon tabs and `?view=` URL sync:

| View | Status |
|------|--------|
| Finances | Implemented — month widgets, section nav, sticky toolbar |
| Training | Coming-soon shell |
| Health | Coming-soon shell |
| Task | Coming-soon shell |

Source of truth, tab UI, and how to add a module: [dashboard-views.md](dashboard-views.md). Do not invent Training / Health / Task schemas or sidebar nav groups until those modules are designed. Sections below describe the **Finances** module.

---

## 4. Database Schema Architecture

### `expenses` Table
* `id` (Primary Key)
* `merchant_name` (String)
* `invoice_number` (String, Nullable)
* `receipt_hash` (String, Unique) - SHA-256 hash.
* `date_time` (Timestamp)
* `subtotal` (Decimal)
* `total_tax` (Decimal)
* `discount_total` (Decimal)
* `rounding_amount` (Decimal)
* `total_amount` (Decimal)
* `currency` (String)
* `original_currency` (String, Nullable) - Printed source currency before conversion.
* `original_total_amount` (Decimal, Nullable) - Extracted total in the printed source currency.
* `currency_conversion_status` (String) - `not_required`, `converted`, `pending`, or `failed`.
* `currency_conversion_rate` (Decimal, Nullable) - Exact rate used as MYR per source unit.
* `currency_conversion_date` (Date, Nullable) - Provider effective date used for the rate.
* `currency_conversion_provider` (String, Nullable)
* `currency_conversion_fetched_at` (Timestamp, Nullable)
* `payment_method_id` (Foreign Key → payment_methods.id, Nullable)
* `source` (String) - `manual` or `whatsapp`.
* `whatsapp_sender` (String, Nullable)
* `whatsapp_message_id` (String, Unique, Nullable) - Evolution message id for media deduplication.
* `family_member_id` (Foreign Key → family_members.id, Nullable) — **Uploaded By**; null = Primary
* `original_filename` (String, Nullable)
* `image_path` (String, Nullable) - Original image or PDF path; null for text-only manual expenses.
* `file_mime_type` (String, Nullable)
* `file_page_count` (Unsigned Small Integer, Nullable) - Populated for inspected PDFs.

### `expense_items` Table
* `id` (Primary Key)
* `expense_id` (Foreign Key -> expenses.id)
* `label_id` (Foreign Key -> labels.id, Nullable)
* `description` (String)
* `quantity` (Integer)
* `unit_price` (Decimal)
* `line_total` (Decimal)
* `warranty_expiry_date` (Date, Nullable)
* `serial_number` (String, Nullable)

---

## 5. Core Features & Automation Workflows (Finances)

### 5.1. Headless Ingestion & Webhooks
* **Evolution API Integration:** POST webhook (`/api/webhooks/whatsapp`) to Laravel, bypassing UI.
* **WhatsApp media receipts:** Image or PDF media download → detected MIME validation → pending `Expense` → batched document ack → `ExtractReceiptDataJob` (Ollama vision). PDF files are limited by `PDF_MAX_BYTES` and `PDF_MAX_PAGES`; rejected files are listed in the acknowledgement and never create an expense.
* **WhatsApp PDF extraction:** Accepted PDFs remain stored as PDFs. Poppler `pdfinfo` inspects page count and `pdftocairo` renders pages to JPEG; Ollama extracts each page as JSON, then merges multi-page results before normal expense normalization, currency conversion, and Label matching.
* **WhatsApp manual text expenses:** Fixed `merchant[, payment];` + `item, qty, line_total;` format → pending `Expense` (no image) → label classification → `requires_manual_review`. See `docs/whatsapp-manual-expense.md`.
* **Attribution:** Allowlisted Family Member senders set `expenses.family_member_id`; Profile/primary senders leave it null (**Uploaded By**). Classic phone JIDs and linked WhatsApp LIDs use the same allowlist. Optional family panel login via WhatsApp OTP — see `docs/household-access.md`.

### 5.2. 100% Offline AI Extraction
* Dispatches a queued job (`ExtractReceiptDataJob`) to the local Ollama HTTP API (`OLLAMA_HOST`, default `http://127.0.0.1:11434`) at `/api/generate`. After source-currency reconciliation, foreign amounts are converted by `CurrencyConversionService` using the configured historical rate provider. Ollama runs as a native host process (see `docs/ollama-setup.md`).

### 5.3. Dynamic Auto-Categorization & Line-Item Splitting
* AI maps individual line items to predefined Finance **Labels**. Filament uses a `Repeater` form component for manual review.

### 5.4. Duplicate Fraud Detection
* Observer generates composite hash: `hash('sha256', $invoice_number . $date_time . $exact_total)`. Database `UNIQUE` constraint prevents insertion.

### 5.5. Recurrings (reminder-first)
* Templates (`Recurring`) and period instances (`RecurringOccurrence`) track bills, subscriptions, instalments, and transfers without auto-creating expenses.
* Daily generation/reminders; after expense `parsed`/`reviewed`, merchant-alias matching completes open occurrences. See `docs/recurrings.md`.

---

## 6. Security & Prompt Architecture Critique

* **Hallucination Mitigation:** HTTP client logic must include regex to strip markdown blocks before `json_decode()`. Pass `"format": "json"` in the Ollama API request payload.
* **Currency evidence:** PDF text is checked for printed ISO codes and currency markers before a focused vision currency pass; prompts must never default to MYR. A bare `$` is treated as USD only when no competing dollar-currency marker is present, while conflicting or missing evidence returns null. Source amounts remain unchanged until conversion succeeds, and a failed or pending conversion remains source-denominated and is excluded from MYR analytics.
* **Webhook Authentication:** Evolution callbacks accept only `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>`. The inbound secret must be a distinct 32+ character value from `EVOLUTION_API_KEY`, which matches Evolution's `AUTHENTICATION_API_KEY`; query-string and raw-token forms are rejected. Other external webhooks require their provider-specific signature, bearer, or private-network boundary.
* **Household access:** Single panel with Primary vs Family Member roles (`HouseholdRole`); family members mutate only their attributed expenses, assigned budgets, and assigned recurrings. Resource edit audit records the authenticated Primary or Family Member separately from expense spender attribution. See `docs/household-access.md` and `docs/resource-edit-audit.md`.
* **Storage Limits:** Enforce detected MIME type validation, a maximum file size (`PDF_MAX_BYTES`, default 10 MB), and a PDF page limit (`PDF_MAX_PAGES`, default 4) to prevent memory exhaustion during Base64 encoding and multi-page rendering. Configure absolute Poppler binary paths for Windows queue workers.

---

## 7. Infrastructure, Testing & Monitoring

### 7.1. Local services & orchestration
* **Application / DB / queues:** Windows host development runs PHP via `npm run dev:full` with SQLite (default) and a `database` queue connection. `queue:listen` handles `default`, `whatsapp`, and `receipts`. `reverb:start` serves websockets on **8081** for expense table live sync and database-notification inbox refresh (see [`realtime-broadcasting.md`](realtime-broadcasting.md)).
* **Exchange-rate provider:** Configure `CURRENCY_API_BASE_URL`, `CURRENCY_API_KEY`, timeout/connect timeout, and cache TTL in deployment configuration. Historical lookups use the receipt date; `receipts:convert-currency --dry-run` inspects existing foreign rows without requesting rates or mutating data. A targeted legacy row whose source was previously stored incorrectly can use `receipts:convert-currency <id> --source-currency=USD --dry-run` before the approved conversion run.
* **Ollama (OCR):** Native host process on `http://127.0.0.1:11434` with vision model `qwen2.5vl:7b` (see `docs/ollama-setup.md`).
* **Evolution (WhatsApp):** Native host process on `http://127.0.0.1:8080` via `npm run evolution` (see `docs/evolution-local-windows.md`). Do not put Reverb on 8080.
* **Reverb (Echo):** Native host process on `http://127.0.0.1:8081`. Filament Echo refreshes List Expenses, Upload Receipts, Recent Receipts, Monthly Spending Overview, Monthly Spending Trend, Spending by Label, Budget Performance, Top Merchants, Spending by Payment Method, Receipts by Upload Source, Due Recurrings, and Recurring Month Snapshot on expense create/status change, and the database-notifications inbox on `DatabaseNotificationsSent`. Do not use Pusher Cloud.
* **Poppler (PDF OCR):** Native Windows command-line tools `pdfinfo.exe`, `pdftotext.exe`, and `pdftocairo.exe`, configured through `PDFINFO_BINARY`, `PDFTOTEXT_BINARY`, and `PDFTOCAIRO_BINARY`; `pdfinfo` and a Poppler renderer (`pdftocairo`, with `pdftoppm` fallback through `PDFTOPPM_BINARY`) are required by the queue worker, while `pdftotext` supplies additional currency evidence when available.

### 7.2. Queue Monitoring & Error Handling
* **Laravel Horizon:** Install and configure Horizon to monitor Redis queues. AI parsing is heavily resource-dependent and prone to timeouts.
* **Job Retries & Fallbacks:** Configure `ExtractReceiptDataJob` with `$tries = 3` and a backoff delay. Implement a `failed()` method that catches unparseable receipts and updates the database status to `requires_manual_review`.

### 7.3. Automated Testing Suite
* **Pest PHP:** Implement Pest for PSR-compliant, expressive test coverage.
* **API Mocking:** Do not trigger actual Ollama, Evolution, Reverb, or exchange-rate providers during test execution. Use Laravel's `Http::fake()` to mock expected JSON payloads from the AI and rate provider so tests run in milliseconds rather than minutes. Broadcasting tests use `BROADCAST_CONNECTION=null` and `Event::fake()`.
* **Webhook Feature Tests:** Assert that authorized image/PDF payloads from the Evolution API correctly dispatch media jobs, non-PDF documents are ignored, linked/unlinked LIDs follow the allowlist rules, and unauthorized requests return `401 Unauthorized`.

### 7.4. Data Backup & Retention Strategy
* **Database Snapshots:** Utilize `spatie/laravel-backup` to run daily scheduled backups of the PostgreSQL database (and configured files), archiving them to a separate, secure local directory or secondary cloud disk.
* **Backup catalog:** Successful backups are registered in the `backups` table (`Backup` model / `BackupService`), including scheduled runs via `RegisterScheduledBackupCatalog` on `BackupWasSuccessful`. Manage download / restore / delete from Filament **Tools → Backups**.
* **Resource edit audit:** Supported resource tables store the latest authenticated editor in `edited_by`, display **Edited By** from the user’s username, and use `updated_at` for **Edited At** and newest-first recency. See `docs/resource-edit-audit.md`.
* **Restore tokens:** Plain restore tokens are `{selector}.{secret}` and are shown once; the catalog stores `restore_token_lookup` plus `restore_token_hash`. Guest restore (no users) uses indexed selector lookup and the auth-menu Restore Backup modal — see `docs/backups-and-danger-zone.md`.
* **Danger Zone:** Profile Danger Zone creates a final backup then wipes account data (`AccountDangerZoneService`).
* **Orphaned File Cleanup:** Implement a scheduled task to purge base64-encoded image strings from temporary cache stores once the OCR pipeline completes to prevent disk bloat.

### 7.5. Service Status (health probes)

* **Filament UI:** **Tools → Service Status** — summary report + per-service 30-day uptime bars (12h pieces). See `docs/service-status.md`.
* **Probes:** `health:probe` every 15 minutes stores samples in `service_health_samples` (App, Database, Ollama, Evolution, Queue, Reverb when `BROADCAST_CONNECTION=reverb`).
* **Retention:** `health:prune` daily; 30-day sample window matches the visible chart.
* **Tests:** Mock Ollama/Evolution/Reverb with `Http::fake()`; do not hit real services in CI.
