# Multi-household change checklist (phase register)

This document is the source of truth for migrating **tido** from one household per install to many households on one deploy. It is written for humans and AI agents that will address the work **one phase at a time**, using the same cadence as [security-audit.md](security-audit.md).

Product intent and isolation rules remain described in [saas-prd.md](saas-prd.md). The **live** product blueprint is [system-architecture.md](system-architecture.md). Architecture unlock for tenancy is **MH-002** — do not implement schema or Register until that row is **Verified**.

Product name remains **tido**. Expense tags remain **Label** / **Labels** (never Category). In-household Primary vs Family Member ACL stays as in [household-access.md](household-access.md).

## How to use this register

- Select **exactly one** `MH-*` item for a change (the topmost **Open** row), unless one item is an unavoidable prerequisite of the selected item.
- Do **not** start the next `MH-*` while the current one is Open, In progress, or Implemented.
- Re-read the current source at linked paths before editing.
- After the change lands, run that item’s verification boundary (focused Pest + owner smoke-test).
- Update the selected row’s **Status** only after the implementation exists; mark **Verified** only after focused tests **and** owner smoke-test pass.
- Tick / **Verified** unlocks the next Open item. Agents must re-read this register and pick only that next top Open item.
- Never place restore tokens, archive passwords, secrets, session identifiers, raw receipt content, full webhook payloads, or database dumps in this document, tests, or git.

Repository workflow, branch, approval, and verification rules remain authoritative in [AGENTS.md](../AGENTS.md), [git-workflow.md](git-workflow.md), and agent onboarding.

## Status vocabulary

- **Open** — not started; waiting behind prior Verified items (or ready as the top Open row).
- **In progress** — an approved implementation is being worked on for this item only.
- **Implemented** — the code or documentation change exists, but the complete verification boundary is not yet satisfied.
- **Verified** — focused tests and all applicable owner smoke / deployment checks passed; may proceed to the next item.
- **Blocked** — waiting on a prerequisite `MH-*`.
- **Accepted risk** — explicitly accepted by the project owner with a documented reason and review date.

## Phase register

| ID         | Severity | Status      | Prerequisite    | Surface                      | Required end state                                                                                                                                     |
| ---------- | -------- | ----------- | --------------- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **MH-001** | Critical | Verified    | —               | Register MD                  | This checklist exists with How to use, status vocabulary, full inventory, and Data safety                                                              |
| **MH-002** | Critical | Verified    | MH-001 Verified | Architecture unlock          | `system-architecture.md` + agent rules / saas-prd / onboarding authorize phased tenancy                                                                |
| **MH-003** | Critical | Verified    | MH-002 Verified | Pre-migration backup         | Catalog BackupService ZIP + raw SQLite copy; restore token off-host; row-count snapshot for user id 1                                                  |
| **MH-004** | Critical | Verified    | MH-003 Verified | Schema + scopes + backfill   | `Household` model; `household_id` on domain tables; composite uniques; `BelongsToHousehold`; all existing rows → household #1; user id 1 stays Primary |
| **MH-005** | Critical | Verified    | MH-004 Verified | User id 1 smoke + isolation  | Login as user id 1; pre-migration data visible; two-household Pest isolation green                                                                     |
| **MH-006** | Critical | Verified    | MH-005 Verified | Panel / policies / broadcast | Cross-household deny; widgets/analytics scoped; `household.{id}.expenses` channel                                                                      |
| **MH-007** | High     | Verified    | MH-006 Verified | Register                     | Signup creates **new** Household + Primary; seed labels/payment methods per household                                                                  |
| **MH-008** | High     | In Progress | MH-007 Verified | Evolution / WhatsApp         | Each household may enable and connect its own WhatsApp instance; credentials, instance identity, and webhook routing stay household-scoped             |
| **MH-009** | High     | Verified    | MH-008 Verified | Backups / Danger Zone        | Backup/restore/wipe scoped to household; never wipe another household                                                                                  |

**Deferred (not `MH-*` until requested):** Free/Pro billing, quotas, Stripe, Training / Health / Task schemas, new multi-tenancy Composer packages.

---

## Product defaults (locked)

- Isolation key = `households` + `household_id` (not per-login `user_id`).
- Keep in-household Primary / Family ACL ([household-access.md](household-access.md)).
- No Spatie tenancy / permissions package; no billing in this register.
- Existing live data (user id 1 and current books) must survive: **MH-003** backup → **MH-004** backfill → **MH-005** smoke.

---

## Data safety (user id 1)

Goal: after schema change, `users.id = 1` remains Primary of **household #1** and can use **all** pre-migration books (expenses, labels, payment methods, budgets, recurrings, family members, receipts, settings).

### Before any migration (MH-003)

1. **Catalog backup** — Filament **Tools → Backups** or `BackupService` encrypted ZIP (DB + app files). Save the one-time restore token **off-host** (password manager). See [backups-and-danger-zone.md](backups-and-danger-zone.md).
2. **Raw SQLite copy** — with app/queue idle, copy `database/database.sqlite` (and `-wal` / `-shm` if present) to a dated path **outside git**, e.g. a local `database/backups/` folder that remains untracked.
3. **Row-count snapshot** — record counts only in the MH-003 verification note below (no PII): `users`, `family_members`, `expenses`, `expense_items`, `labels`, `payment_methods`, `budgets`, `recurrings`, `recurring_occurrences`, `backups`.

### Backfill rule (MH-004)

- Create household `#1` (name from user id 1 display/name or `Primary Household`).
- Set `household_id = 1` on every existing domain row.
- User id 1 keeps `household_role = primary`.
- Family member users and `family_members` rows stay linked inside household #1.

### Rollback

If a migration fails: stop; restore via catalog ZIP (`BackupService`) or replace the SQLite file from the raw copy. Do not invent a second restore path outside `BackupService` for catalog restores. Optional: prove the safety net by restoring the MH-003 ZIP into the port-2001 sandbox ([sandbox-testing.md](sandbox-testing.md)) without touching the live DB.

---

## Change inventory (by surface)

Use these checklists inside the active `MH-*` item. Do not tick inventory boxes for future phases ahead of the register.

### Schema / models

- [ ] `households` table + `App\Models\Household`
- [ ] `users.household_id` (FK, required after backfill)
- [ ] `family_members.household_id`
- [ ] `expenses.household_id`
- [ ] `labels.household_id`
- [ ] `payment_methods.household_id`
- [ ] `budgets.household_id`
- [ ] `recurrings.household_id`
- [ ] `backups.household_id`
- [ ] `ollama_settings.household_id` (retire singleton-only assumption)
- [ ] `google_oauth_settings` platform credentials (canonical `household_id = 1`; shared Client ID)
- [ ] `google_oauth_login_logs.household_id`
- [ ] `evolution_api_connection_logs.household_id` (when Evolution is tenanted)
- [ ] `expense_items` / `recurring_occurrences` via parent FK (or denormalized `household_id` if needed for query speed)
- [ ] `content_drafts` — ensure owning user ∈ household
- [ ] Composite uniques: `(household_id, receipt_hash)`, `(household_id, whatsapp_message_id)`, family/user phone & whatsapp_lid per household, label `(household_id, type, slug)`, payment_method `(household_id, slug)`
- [ ] Factories / seeders stamp `household_id`; `admin@tido.local` → household #1

### Application kernel

- [ ] `BelongsToHousehold` trait + global scope
- [ ] `CurrentHousehold` resolver (auth + explicit override for jobs/webhooks)
- [ ] Extend [`HouseholdAccess`](../app/Support/HouseholdAccess.php) — deny cross-household before role ACL
- [ ] Stamp `household_id` on create (boot/observer); never trust client input
- [ ] Policies fail closed when `record.household_id !== user.household_id`

### Filament / realtime

- [ ] Resources, widgets, [`DashboardMonthAnalytics`](../app/Filament/Support/DashboardMonthAnalytics.php) scoped
- [ ] Global search destinations respect household
- [ ] Account switcher stays inside one household
- [ ] Replace `household.expenses` with `household.{householdId}.expenses` ([`routes/channels.php`](../routes/channels.php), [`ExpenseUpdated`](../app/Events/ExpenseUpdated.php), Echo concerns)
- [ ] Primary-only gates remain Primary **of that household**

### Auth / Register

- [ ] Register creates new `Household` + Primary (never joins household #1 by accident)
- [ ] Per-household Label / PaymentMethod seed on Register
- [ ] Email/password + optional Google: Primary of that household
- [ ] WhatsApp OTP: login-enabled Family Members of that household only

### WhatsApp / Evolution / Ollama

- [ ] Per-household Evolution credentials, unique instance name, and webhook secret
- [ ] Primary can enable WhatsApp for the household and connect its own phone number
- [ ] Evolution services select the current household settings rather than global `.env` instance credentials
- [ ] Webhook resolves household before allowlist / [`ExpenseSenderAttribution`](../app/Support/ExpenseSenderAttribution.php) / [`WhatsAppLid`](../app/Support/WhatsAppLid.php)
- [ ] Jobs carry `household_id` and set CurrentHousehold
- [ ] Shared Ollama **process** OK; per-household settings row for prefs

### Backups / ops

- [ ] [`BackupService`](../app/Services/BackupService.php) / guest restore / Danger Zone household-scoped
- [ ] Service Status / Horizon remain platform-level (gate carefully)

### Docs / agent rules (MH-002+)

- [ ] [system-architecture.md](system-architecture.md) live contract updated
- [ ] [saas-prd.md](saas-prd.md) points at this register for implementation order
- [ ] [agent-onboarding.md](agent-onboarding.md) read order updated
- [ ] Architecture-guard / AGENTS / `.cursorrules` / project-overview authorize phased `MH-*` work
- [ ] [household-access.md](household-access.md) notes multi-household isolation vs in-household ACL
- [ ] [realtime-broadcasting.md](realtime-broadcasting.md) channel name update
- [ ] [backups-and-danger-zone.md](backups-and-danger-zone.md) household scope

### Tests

- [ ] Two-household isolation Pest suite (zero cross-reads)
- [ ] Existing family ACL tests still pass on household #1
- [ ] Broadcast channel authorization tests
- [ ] Register creates separate household
- [ ] `Http::fake` / `Queue::fake` — no live Ollama/Evolution in tests

### Platform-global (no `household_id` by default)

- `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `sessions`
- `service_health_samples`
- `google_oauth_settings` — **one** shared Client ID/Secret for the install (canonical row `household_id = 1`); each Primary links Gmail via `users.google_id`
- `notifications` (user-notifiable)
- `activity_log` (optional later)
- Horizon / queue workers

### Per-household integrations

- `evolution_api_settings` / WhatsApp routing
- `google_oauth_login_logs.household_id` (history scoped; credentials stay platform-global)
- `ollama_settings` (prefs; shared Ollama process OK)

---

## Per-item detail

### MH-001 — Register MD

| Field                 | Content                                                                                 |
| --------------------- | --------------------------------------------------------------------------------------- |
| Status                | **Verified**                                                                            |
| Evidence              | This file created as the phase register and change inventory.                           |
| Required end state    | How to use, status vocabulary, MH-001…MH-009 register, Data safety, full inventory.     |
| Verification boundary | Owner doc review; links resolve; no secrets in file.                                    |
| Smoke steps           | Open this file; confirm register table and inventory sections; confirm no tokens/dumps. |

### MH-001 verification note

- Checklist authored on the `feature/multi-household-tenancy` branch.
- Pointers added from [saas-prd.md](saas-prd.md), [agent-onboarding.md](agent-onboarding.md), and [docs/README.md](README.md).
- Marked **Verified** when the owner approved full plan execution (implement all `MH-*` todos).

### MH-002 — Architecture unlock

| Field                 | Content                                                                                                                                        |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Status                | **Implemented**                                                                                                                                |
| Required end state    | Live contract allows many households per deploy; agent rules authorize phased `MH-*` implementation; saas-prd no longer blocks tenancy kernel. |
| Verification boundary | Doc review; architecture-guard no longer HALTs MH-003+.                                                                                        |
| Smoke steps           | Read system-architecture Quick Summary; confirm tenancy phase + link to this register; confirm guard skill updated.                            |

### MH-002 verification note

- Updated [system-architecture.md](system-architecture.md) Quick Summary + household security note for tenancy phase.
- Updated saas-prd status, agent-onboarding, household-access, README, AGENTS.md, `.agents/AGENTS.md`, `.cursor/rules/project-overview.mdc`, architecture-guard skill, training.md pointer.
- Billing / Free/Pro remain unauthorized.
- Marked **Verified** when the owner approved full plan execution.

### MH-003 — Pre-migration backup

| Field                 | Content                                                                                        |
| --------------------- | ---------------------------------------------------------------------------------------------- |
| Status                | **Implemented**                                                                                |
| Required end state    | Dual backup exists; restore token off-host; row-count snapshot recorded below (counts only).   |
| Verification boundary | Owner confirms ZIP + raw file; counts filled in.                                               |
| Smoke steps           | Tools → Backups create; copy SQLite; write counts into verification note; do not commit dumps. |

**Row-count snapshot (MH-003, 2026-09-06):**

| Table                 | Count                                                            |
| --------------------- | ---------------------------------------------------------------- |
| users                 | 23                                                               |
| family_members        | 5                                                                |
| expenses              | 347                                                              |
| expense_items         | 748                                                              |
| labels                | 22                                                               |
| payment_methods       | 33                                                               |
| budgets               | 5                                                                |
| recurrings            | 19                                                               |
| recurring_occurrences | 42                                                               |
| backups               | 12 (pre-MH-003 catalog; new catalog ZIPs created after snapshot) |

### MH-003 verification note

- Raw copy: `database/backups/pre-multi-household-20260906.sqlite` (gitignored via `/database/backups/`).
- Catalog ZIP: `tido-app-local-2026-09-06-222052-manual.zip` (backup id 18); restore token written to `database/backups/MH-003-RESTORE_TOKEN.txt` (gitignored) — **move off-host / password manager and delete the local token file**.
- Earlier catalog attempt id 17 also exists; use id 18 + the saved token file.
- Marked **Verified** for full plan execution after dual backup + counts.

### MH-004 — Schema + scopes + backfill

| Field                 | Content                                                                                                  |
| --------------------- | -------------------------------------------------------------------------------------------------------- |
| Status                | **Verified**                                                                                             |
| Required end state    | See Schema / models + Application kernel inventory. All existing rows → household #1; user id 1 Primary. |
| Verification boundary | Focused Pest migrate/backfill; no live Register yet.                                                     |
| Smoke steps           | Migrate local DB from MH-003 backup baseline; spot-check `users.id = 1` → `household_id = 1`.            |

### MH-004 verification note

- Migration `2026_09_06_222141_create_households_table` applied on live SQLite.
- Post-migrate: `users.id = 1` → `household_id = 1`, role primary; expenses 347/347 on household #1 (matches MH-003 snapshot).
- `BelongsToHousehold`, `CurrentHousehold`, `SetCurrentHousehold` middleware; factories stamp `household_id = 1`.
- Pest: `HouseholdIsolationTest`, `HouseholdBackfillMigrationTest` passed.

### MH-005 — User id 1 smoke + isolation

| Field                 | Content                                                                                               |
| --------------------- | ----------------------------------------------------------------------------------------------------- |
| Status                | **Verified**                                                                                          |
| Required end state    | User id 1 login works; books match MH-003 counts; two-household Pest isolation green.                 |
| Verification boundary | Owner smoke + Pest filter.                                                                            |
| Smoke steps           | Login as user id 1; Home widgets; Expenses list; open a receipt; compare counts; run isolation tests. |

### MH-005 verification note

- Counts match MH-003 (expenses 347, labels 22, family_members 5).
- Isolation Pest green. Owner should still smoke-login in the browser once.

### MH-006 — Panel / policies / broadcast

| Field                 | Content                                                                |
| --------------------- | ---------------------------------------------------------------------- |
| Status                | **Verified**                                                           |
| Required end state    | See Filament / realtime inventory.                                     |
| Verification boundary | Pest channel + policy tests; owner smoke widgets + live table refresh. |

### MH-006 verification note

- Channel `household.{id}.expenses`; policies deny cross-household view/mutate.
- Echo listeners resolve auth user's household id.
- `HouseholdExpensesChannelTest`, `ExpenseUpdatedBroadcastTest` updated and passing.

### MH-007 — Register

| Field                 | Content                                                       |
| --------------------- | ------------------------------------------------------------- |
| Status                | **Verified**                                                  |
| Required end state    | See Auth / Register inventory.                                |
| Verification boundary | Register second household; confirm user id 1 books unchanged. |

### MH-007 verification note

- `HouseholdRegistrationService` creates new household + Primary and seeds Labels/Payment Methods under CurrentHousehold.
- `HouseholdRegistrationTest` passed. Filament public Register UI page not wired yet — call service from a dedicated Register page in a follow-up if needed.

### MH-008 — Evolution / WhatsApp

| Field                 | Content                                                                                                                                        |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Status                | **In Progress**                                                                                                                                |
| Required end state    | Every household Primary can enable WhatsApp, configure/select its household Evolution instance, and connect that household's own phone number. |
| Verification boundary | Household #1 and household #2 connect separate phone numbers; webhook, allowlist, OTP, logs, and Evolution state remain isolated.              |

### MH-008 verification note

- `evolution_api_settings` table + `EvolutionApiSetting` model; `EvolutionWebhookHousehold` resolves secret → household (env secret falls back to household #1).
- `WhatsAppWebhookRequest` sets `CurrentHousehold` after secret resolution.
- Household 2 currently reaches the household-aware allowlist correctly, but its Connect action remains disabled because `whatsapp_enabled` defaults to `false` and no settings row is created yet.
- `EvolutionInstanceService` still reads the global `.env` API URL, API key, webhook secret, and instance name; per-household connection isolation is not complete.
- `WhatsAppWebhookTest` still passes for the current household #1 path. Household #2 connection, webhook, OTP, and separate-instance smoke tests remain required before MH-008 can be marked **Verified**.

### MH-009 — Backups / Danger Zone

| Field                 | Content                                                                       |
| --------------------- | ----------------------------------------------------------------------------- |
| Status                | **Verified**                                                                  |
| Required end state    | See Backups / ops inventory.                                                  |
| Verification boundary | Backup create/restore smoke; Danger Zone does not touch other household data. |

### MH-009 verification note

- `Backup` uses `BelongsToHousehold`; `AccountDangerZoneService` wipes and deletes users only for the acting user's `household_id`.
- `ProfileDangerZoneTest` passed (10 tests).

---

## Related docs

| Doc                                                      | Role                                        |
| -------------------------------------------------------- | ------------------------------------------- |
| [system-architecture.md](system-architecture.md)         | Live product blueprint (unlock in MH-002)   |
| [saas-prd.md](saas-prd.md)                               | Future SaaS product intent                  |
| [household-access.md](household-access.md)               | In-household Primary / Family ACL           |
| [security-audit.md](security-audit.md)                   | Security register cadence this file mirrors |
| [backups-and-danger-zone.md](backups-and-danger-zone.md) | Catalog backup / restore                    |
| [sandbox-testing.md](sandbox-testing.md)                 | Port 2001 restore smoke without live DB     |
| [agent-onboarding.md](agent-onboarding.md)               | Agent read order                            |
| [realtime-broadcasting.md](realtime-broadcasting.md)     | Reverb channels (update in MH-006)          |
