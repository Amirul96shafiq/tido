<p align="center">
  <img src="public/images/tido_dark_logo.png#gh-light-mode-only" alt="tido" width="280">
  <img src="public/images/tido_light_logo.png#gh-dark-mode-only" alt="tido" width="280">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Filament-5-F59E0B?logo=filament&logoColor=white" alt="Filament 5">
  <img src="https://img.shields.io/badge/Pest-3-1A2C32?logo=pest&logoColor=white" alt="Pest 3">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="License MIT">
</p>

<p align="center">
  <strong>Keep it tidy. Get it done.</strong><br>
  Where tidy preparation meets finished work, then tido (sleep)
</p>

<p align="center">
tido is a localized, single-tenant MYR expense tracker built for frictionless financial logging. Ingest receipts autonomously via WhatsApp webhooks or scheduled Google Drive syncs (coming soon!), and bypass third-party APIs completely with on-device OCR parsing powered by Ollama (minicpm-v) (coming soon!). Manage parsed line items as labels, track strict budgets, and review analytics instantly within a streamlined Filament dashboard.
</p>

## Table of Contents

- [Features](#features)
- [Stack](#stack)
- [Architecture](#architecture)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Testing](#testing)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Features

- Receipt ingestion from WhatsApp (Evolution API), Google Drive **scheduled** sync (every 15m), and admin upload
- Local OCR via Ollama (`minicpm-v`) with JSON-formatted extraction
- Line-item **Labels**, duplicate detection (`receipt_hash`), and manual review
- Per-label budgets with WhatsApp threshold alerts
- Month-scoped dashboard analytics and spending forecast
- Redis queues via Laravel Horizon (`default`, `receipts`, `whatsapp`)
- Form draft auto-save and crash recovery on Filament Create/Edit
- Spatie backups, one-time restore tokens, guest restore, and profile Danger Zone

## Stack

| Layer | Technology |
|-------|------------|
| App | Laravel 12, PHP 8.2+ |
| Admin UI | Filament v5, Livewire 4, Tailwind CSS v4 |
| Database | PostgreSQL 17 (Sail); SQLite for quick local |
| Queues | Redis + Laravel Horizon (`default`, `whatsapp`, `receipts`) |
| OCR | Ollama (`minicpm-v`) |
| WhatsApp | Evolution API |
| Drive | `masbug/flysystem-google-drive-ext` |
| Backups / audit | Spatie Laravel Backup, Spatie Activity Log |
| Tests | Pest v3 |
| Dev env | Laravel Sail / Docker Compose |

## Architecture

```mermaid
flowchart LR
  wa[WhatsApp_webhook] --> pending[Pending_Invoice]
  drive[Drive_sync_15m] --> pending
  upload[Admin_upload] --> pending
  pending --> job[ExtractReceiptDataJob]
  job --> ollama[Ollama_vision]
  ollama --> items[Labels_and_line_items]
  items --> review[Parsed_or_manual_review]
```

Invoice statuses: `pending` → `parsed` → `reviewed` (or `requires_manual_review` / `failed`). Duplicates use SHA-256 `receipt_hash` (number + datetime + total). Expense tags are the **`Label`** model / `labels` table (UI: **Label** / **Labels**).

Scheduled jobs (`routes/console.php`): Drive sync every 15 minutes; `backup:run` daily 02:00; `backup:clean` daily 03:00.

Full blueprint: [docs/system-architecture.md](docs/system-architecture.md).

## Installation

### Prerequisites

- PHP 8.2+, Composer, Node.js
- Docker Desktop (for Sail stack: PostgreSQL, Redis, Ollama, Evolution)
- NVIDIA GPU + Container Toolkit recommended for Ollama OCR

### Sail (recommended)

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
```

One-shot alternative (sqlite defaults unless `.env` is adjusted first): `composer setup`.

Configure Sail in `.env` (defaults in `.env.example` use sqlite / database queue):

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_DATABASE=tido
DB_USERNAME=sail
DB_PASSWORD=password
QUEUE_CONNECTION=redis
REDIS_HOST=redis
EVOLUTION_API_URL=http://evolution-api:8080
OLLAMA_HOST=http://ollama:11434
```

Then migrate, seed, build assets, and start workers + scheduler:

```bash
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
./vendor/bin/sail artisan horizon
./vendor/bin/sail artisan schedule:work
```

Horizon listens on queues `default`, `whatsapp`, and `receipts`. Without `schedule:work` (or a cron entry for `schedule:run`), Drive sync and daily backups will not run.

Pull the vision model (once):

```bash
docker exec -it tido-ollama-1 ollama pull minicpm-v
curl http://localhost:11434/api/tags
```

Default seeded login: `admin@tido.local` / `password`.

Outside `local`, allow Horizon dashboard access by adding emails to the `viewHorizon` gate in [`app/Providers/HorizonServiceProvider.php`](app/Providers/HorizonServiceProvider.php) (the allowlist starts empty).

<details>
<summary>Register Evolution webhook (Sail)</summary>

Create/pair the instance first (**Settings → WhatsApp Connection** in admin)

Full guide: [docs/evolution-api-setup.md](docs/evolution-api-setup.md).

</details>

<details>
<summary>Windows host (no Docker)</summary>

| Terminal | Command |
|----------|---------|
| 1 | `npm run dev:full` — Vite + `artisan serve --port=2000` + queue listener on `default,whatsapp,receipts` |
| 2 | `npm run evolution` — Evolution on `http://127.0.0.1:8080` |
| 3 (optional) | `php artisan schedule:work` — Drive sync + backups |

Or all-in-one: `npm run dev:whatsapp`. Webhook URL: `http://127.0.0.1:2000/api/webhooks/whatsapp`.

Also available: `composer run dev` (serve + queue + Pail + Vite, no Evolution).

See [docs/evolution-local-windows.md](docs/evolution-local-windows.md).

</details>

Integration setup guides: [Ollama](docs/ollama-setup.md) · [Evolution API](docs/evolution-api-setup.md) · [Google Drive](docs/google-drive-setup.md).

## Usage

Admin nav:

- **Finances** — Invoices, Budgets
- **Settings** — Labels, WhatsApp Connection, Backups

**WhatsApp OTP login:** Pair Evolution → set `PERSONAL_WHATSAPP_NUMBER` (and match the user’s phone) → `php artisan whatsapp:ping` → sign in with OTP at `/admin/login`.

**Backups:** Cataloged ZIPs under Settings → Backups. Restore tokens are shown once (email/UI); only a hash is stored. After Danger Zone account wipe, guest restore is available when no users exist. Details: [docs/backups-and-danger-zone.md](docs/backups-and-danger-zone.md).

Useful commands:

```bash
php artisan horizon
php artisan schedule:work
php artisan whatsapp:ping
php artisan backup:run
php artisan test --compact
composer test
npm run build
npm run dev:full
```

## Configuration

Copy `.env.example` and set values for your environment. Notable groups:

<details>
<summary>Notable environment variables</summary>

| Variable | Purpose |
|----------|---------|
| `DB_*` | Database (pgsql for Sail) |
| `QUEUE_CONNECTION` / `REDIS_*` | Horizon queues (`default`, `whatsapp`, `receipts`) |
| `SESSION_LIFETIME` | Session minutes (default `10080` = 7 days) |
| `EVOLUTION_API_URL` | Evolution base URL |
| `EVOLUTION_API_KEY` | API + webhook Bearer token |
| `EVOLUTION_INSTANCE_NAME` | Instance name (default `tido`) |
| `PERSONAL_WHATSAPP_NUMBER` | Primary number: OTP login, panel identity, budget alerts, seeded admin phone |
| `PERSONAL_WHATSAPP_EXTRA_NUMBERS` | Extra numbers for receipt import / bot only (no panel OTP) |
| `OLLAMA_HOST` | Ollama HTTP API (default `http://ollama:11434`) |
| `OLLAMA_MODEL` | Vision model (default `minicpm-v`) |
| `GOOGLE_DRIVE_CLIENT_ID` | Drive OAuth client |
| `GOOGLE_DRIVE_CLIENT_SECRET` | Drive OAuth secret |
| `GOOGLE_DRIVE_REFRESH_TOKEN` | Drive refresh token |
| `GOOGLE_DRIVE_FOLDER_ID` | Folder polled by `SyncGoogleDriveJob` (not push/Pub/Sub) |

</details>

## Testing

```bash
php artisan test --compact
composer test
vendor/bin/pint --dirty --format agent
```

Tests use in-memory SQLite. Mock external HTTP and queues with `Http::fake()` / `Queue::fake()` — never call live Ollama or Evolution in tests.

## Documentation

Deep docs live under [`docs/`](docs/README.md):

| Doc | Purpose |
|-----|---------|
| [agent-onboarding.md](docs/agent-onboarding.md) | Product map for agents and contributors |
| [system-architecture.md](docs/system-architecture.md) | Architecture blueprint |
| [ollama-setup.md](docs/ollama-setup.md) | Local GPU Ollama / MiniCPM-V |
| [evolution-api-setup.md](docs/evolution-api-setup.md) | Evolution instance + webhook (Sail) |
| [evolution-local-windows.md](docs/evolution-local-windows.md) | Evolution on Windows host |
| [google-drive-setup.md](docs/google-drive-setup.md) | Drive folder sync credentials |
| [backups-and-danger-zone.md](docs/backups-and-danger-zone.md) | Backups, restore tokens, Danger Zone |
| [content-draft-recovery.md](docs/content-draft-recovery.md) | Form draft auto-save / crash recovery |
| [git-workflow.md](docs/git-workflow.md) | Branching and PRs |
| [ui-tooltips.md](docs/ui-tooltips.md) · [ui-dark-theme.md](docs/ui-dark-theme.md) · [ui-empty-states.md](docs/ui-empty-states.md) · [ui-copy-style.md](docs/ui-copy-style.md) · [ui-modal-overlay.md](docs/ui-modal-overlay.md) | Filament UI conventions |

Full index: [docs/README.md](docs/README.md).

## Contributing

1. Update `main`, then branch: `feature/<short-kebab>` or `fix/<short-kebab>`
2. Keep changes focused; run Pint and affected Pest tests
3. Open a **PR into `main`**; delete the branch after merge
4. Do **not** develop features on `main`, `staging`, or `production`
5. Future promotion path (when those servers exist): `main` → `staging` → `production`

Details: [docs/git-workflow.md](docs/git-workflow.md). Coding standards: PSR-12, `declare(strict_types=1);`, Laravel Pint.

## License

tido is open-sourced software licensed under the [MIT license](LICENSE).
