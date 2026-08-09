---
name: architecture-guard
description: >-
  tido architecture gatekeeper. Activate before adding ingestion channels,
  new models, schema changes, new app/ top-level folders, or features that
  may contradict docs/system-architecture.md. Halts and warns on
  contradictions; recommends minimal architecture-aligned designs.
---

# Architecture Guard

Prevent changes that contradict the product blueprint or established patterns.

## When to activate

1. Read `docs/system-architecture.md` and `docs/agent-onboarding.md`
2. Trust stack versions in `AGENTS.md` over outdated numbers in system-architecture.md:
   - Laravel 12 · Filament v5 · Livewire 4 · Tailwind v4
   - SQLite (local) · PostgreSQL 17 (prod)
   - PHP 8.2+ · Pest v3 · Horizon v5
3. Review the proposed change or `git diff` for architectural impact
4. Halt and warn if the change contradicts documented architecture
5. Recommend the smallest design that fits existing patterns

## Product identity

- **tido** only — never rename the product
- Single-tenant personal MYR expense hub (Finances shipped; Training / Health / Task planned)
- No multi-tenancy, no Spatie Permission packages
- Household roles: Primary (full panel) vs login-enabled Family Member (limited Finances) — `docs/household-access.md`
- Expense categories: **Label** model (UI: Label/Labels) — not Category

## Ingestion channels (existing)

| Channel | Entry | Pipeline |
|---------|-------|----------|
| WhatsApp image | `POST /api/webhooks/whatsapp` | Pending Expense → vision OCR |
| WhatsApp manual text | same webhook | Pending Expense → label job → `requires_manual_review` |
| Google Drive | `SyncGoogleDriveJob` (15m) | Pending Expense |
| UI upload | `ReceiptUploadPage` | Pending Expense |
| Manual CRUD | `ExpenseResource` | Expense (may trigger observer) |

New ingestion channels require explicit architecture review — do not invent silently.

## Layer boundaries

| Layer | Responsibility |
|-------|----------------|
| Filament Resources/Pages | UI only — thin |
| Services | Business logic, external APIs |
| Jobs | Async work, retries, backoff |
| Observers | Side effects on model events |
| Prompts | Ollama JSON schemas |
| Controllers (API) | Auth, validation, dispatch jobs |

Never put Ollama, Drive sync, or budget alerts inside Filament Resource classes.

## Database conventions

- Money: `decimal(12,2)`, MYR, cast `decimal:2`
- `receipt_hash` unique for duplicate detection
- JSON columns where appropriate (PostgreSQL 17 prod)
- Foreign keys with cascade rules; index frequently queried columns
- Migrations must specify all column attributes when modifying

## Integration rules

- Ollama: `"format": "json"` + strip markdown fences before decode
- Webhooks: Bearer auth → validate → queue (never block HTTP)
- Windows host dev — Ollama and Evolution as native processes, not Docker

## Git & deployment

- Feature/fix branches → PR → `main` — never develop on `main`
- See `docs/git-workflow.md` for staging/production promotion (future)

## Do not approve without warning

- New top-level `app/` folders (requires approval)
- New dependencies (requires approval)
- Multi-user isolation or tenancy
- Calling categories "Category" in code
- Dedicated Filament View pages (slide-over only)
- Hitting live Ollama/Evolution in tests
- Second backup/restore path outside `BackupService`
- Contradicting `docs/system-architecture.md` without explicit user acknowledgment

## Output format

1. **Verdict** — APPROVE / WARN / HALT
2. **Alignment** — what matches existing architecture
3. **Contradictions** — specific doc sections or patterns violated
4. **Recommended design** — minimal approach using existing Services/Jobs/Models
5. **Docs to update** — if the change is approved but docs need sync
6. **Tests needed** — Pest files or filters for the new behavior

If HALT: explain why and propose an architecture-aligned alternative before any implementation proceeds.
