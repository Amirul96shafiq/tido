# System Architecture: Local Expense & Receipt Tracking Platform

> **Agents:** Start with [agent-onboarding.md](agent-onboarding.md). Cursor rules live in `.cursor/rules/`. Domain skill: `.cursor/skills/tido-domain/`.  
> **Stack note:** Runtime is **Laravel 12**, **PostgreSQL 17**, Filament v5, Livewire 4 (see `AGENTS.md`). Prefer those versions if this blueprint lists older ones.

## Quick Summary
This document defines the architectural blueprint for a localized, highly automated expense tracking system. The primary objective is to ingest, parse, and analyze financial receipts with zero manual data entry. The stack utilizes Laravel for robust API and queue management, FilamentPHP v5 for rapid dashboard generation, and localized AI models (Ollama) for zero-cost, private OCR data extraction.

---

## 1. Core Technology Stack

| Component | Technology | Purpose |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 12 | API routing, ORM, queued jobs, and task scheduling. Must follow PSR-12 coding standards. |
| **Admin Panel / UI** | FilamentPHP v5 | Auto-generation of data tables, upload widgets, and analytical dashboards. Built on Livewire v4. |
| **Cloud Storage** | `masbug/flysystem-google-drive-ext` | Direct integration of Google Drive folders as Laravel `Storage` disks. |
| **AI Parsing Engine** | Ollama (Local) | Zero-cost execution of vision models (e.g., LLaVA, MiniCPM-V) for OCR and data extraction. |
| **Messaging API** | Evolution API | Headless receipt ingestion and system alert broadcasting via WhatsApp. |
| **Database** | PostgreSQL 17 | Relational data storage optimized for JSON operations and strict indexing. |

---

## 2. UI/UX Aesthetic Guidelines (Google Material Minimalist)

The interface utilizes FilamentPHP's native Tailwind CSS theming engine to achieve a minimalist, high-contrast aesthetic similar to Google Workspace products, with native Dark Mode support.

### 2.1. Theme Configuration
* **Typography:** Override default fonts with `Roboto` or `Inter` to match Google's legibility standards.
* **Color Palette:** 
    * **Primary Accent:** Google Blue (`#1a73e8`) for primary buttons and active states.
    * **Light Mode:** High whitespace, `#ffffff` card backgrounds, and very subtle `#f1f3f4` surface backgrounds. Use soft box-shadows (`shadow-sm`) instead of hard borders.
    * **Dark Mode:** Deep grays (`#202124` for background, `#303134` for cards) instead of absolute black to reduce eye strain. Text should be off-white (`#e8eaed`).

### 2.2. Filament Panel Adjustments
* **Navigation:** Configure `->sidebarCollapsibleOnDesktop()` in the Panel Provider to maximize horizontal workspace.
* **Data Tables:** Implement borderless table designs. Use minimalist pagination and hide complex filter menus behind single icon buttons.

---

## 3. Database Schema Architecture

### `invoices` Table
* `id` (Primary Key)
* `merchant_name` (String)
* `receipt_hash` (String, Unique) - SHA-256 hash.
* `date_time` (Timestamp)
* `total_tax` (Decimal)
* `total_amount` (Decimal)
* `google_drive_file_id` (String)

### `invoice_items` Table
* `id` (Primary Key)
* `invoice_id` (Foreign Key -> invoices.id)
* `category_id` (Foreign Key -> categories.id)
* `description` (String)
* `quantity` (Integer)
* `unit_price` (Decimal)
* `line_total` (Decimal)
* `warranty_expiry_date` (Date, Nullable)
* `serial_number` (String, Nullable)

---

## 4. Core Features & Automation Workflows

### 4.1. Headless Ingestion & Webhooks
* **Evolution API Integration:** POST webhook (`/api/webhooks/whatsapp`) to Laravel, bypassing UI.
* **Google Drive Push Notifications:** HTTP POST webhook from Google Cloud Pub/Sub enqueues the extraction job.

### 4.2. 100% Offline AI Extraction
* Dispatches a queued job (`ExtractReceiptDataJob`) to local Ollama endpoint (`http://localhost:11434/api/generate`).

### 4.3. Dynamic Auto-Categorization & Line-Item Splitting
* AI maps individual line items to predefined database categories. Filament uses a `Repeater` form component for manual review.

### 4.4. Duplicate Fraud Detection
* Observer generates composite hash: `hash('sha256', $invoice_number . $date_time . $exact_total)`. Database `UNIQUE` constraint prevents insertion.

---

## 5. Security & Prompt Architecture Critique

* **Hallucination Mitigation:** HTTP client logic must include regex to strip markdown blocks before `json_decode()`. Pass `"format": "json"` in the Ollama API request payload.
* **Webhook Authentication:** Bearer token authorization or IP whitelisting required for Evolution API/Google PubSub endpoints.
* **Storage Limits:** Enforce strict MIME type validation and maximum file size constraints (e.g., 10MB) to prevent memory exhaustion during Base64 encoding.

---

## 6. Infrastructure, Testing & Monitoring

### 6.1. Containerization & Orchestration
* **Docker Compose Architecture:** The project must be containerized to ensure environment parity. A `docker-compose.yml` should define:
    * `laravel.test`: The PHP 8.3 application image.
    * `pgsql`: PostgreSQL 16 database.
    * `redis`: In-memory data store for handling queue connections securely.
    * `ollama`: Official Ollama container with GPU passthrough enabled (`deploy.resources.reservations.devices`) for hardware-accelerated OCR.

### 6.2. Queue Monitoring & Error Handling
* **Laravel Horizon:** Install and configure Horizon to monitor Redis queues. AI parsing is heavily resource-dependent and prone to timeouts.
* **Job Retries & Fallbacks:** Configure `ExtractReceiptDataJob` with `$tries = 3` and a backoff delay. Implement a `failed()` method that catches unparseable receipts and updates the database status to `requires_manual_review`.

### 6.3. Automated Testing Suite
* **Pest PHP:** Implement Pest for PSR-compliant, expressive test coverage.
* **API Mocking:** Do not trigger the actual Ollama instance during test execution. Use Laravel's `Http::fake()` to mock expected JSON payloads from the AI to ensure tests run in milliseconds rather than minutes.
* **Webhook Feature Tests:** Assert that authorized payloads from the Evolution API correctly dispatch the parsing job, and unauthorized requests return `401 Unauthorized`.

### 6.4. Data Backup & Retention Strategy
* **Database Snapshots:** Utilize `spatie/laravel-backup` to run daily scheduled backups of the PostgreSQL database, archiving them to a separate, secure local directory or secondary cloud disk.
* **Orphaned File Cleanup:** Implement a scheduled task to purge base64-encoded image strings from temporary cache stores once the OCR pipeline completes to prevent disk bloat.
