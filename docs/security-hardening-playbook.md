# Security hardening playbook

This is the implementation procedure for the findings in [security-audit.md](security-audit.md). It is intended for Codex, Cursor, Antigravity, and human reviewers working through the register one security item at a time.

## Non-negotiable rule: one finding per change

Select one `SEC-*` identifier before implementation. A branch, change set, test group, and documentation update should address that identifier only.

An adjacent finding may be included only when it is an unavoidable prerequisite. Record the dependency in the selected register row and do not silently expand the scope. For example, a webhook signature change may require a shared request verifier, but it does not authorize unrelated backup or session changes.

Do not:

- make application changes directly on `main`;
- create a second backup or restore path outside `BackupService` and `AccountDangerZoneService`;
- weaken `HouseholdAccess`, `ExpensePolicy`, `BudgetPolicy`, `RecurringPolicy`, or Primary-only gates to simplify a security test;
- add a dependency or update a lockfile without explicit user approval;
- expose a real secret, restore token, session identifier, raw receipt, full webhook payload, or unredacted upstream response in code, tests, docs, or logs;
- mark a finding verified because a request returned `2xx`, a file was uploaded, a job was dispatched, or a UI control was hidden;
- treat a source-only result as proof of a reverse-proxy, firewall, TLS, storage-permission, or production-environment control.

## Required preparation

Before changing code for a finding:

1. Read the selected row in [security-audit.md](security-audit.md), its linked source files, related docs, and the existing focused tests.
2. Read [AGENTS.md](../AGENTS.md), [.codex/CODEX_WORKFLOW.md](../.codex/CODEX_WORKFLOW.md), and [.codex/VERIFICATION.md](../.codex/VERIFICATION.md) when the current task has not already loaded them.
3. Inspect `git status --short --branch --untracked-files=all` and confirm the branch contains no unrelated dirty work.
4. Start from a short-lived `feature/*` or `fix/*` branch based on the current `main`. Do not update `main` from the network unless that action is explicitly approved or required by the user.
5. Trace the full flow: route or UI entry, request validation, authorization, service/job side effects, persistence, logging, response, retry behavior, and existing tests.
6. Preserve the project’s single-tenant model, existing source-of-truth service boundaries, local Evolution/Ollama development setup, and Primary versus Family Member behavior.
7. Define the security invariant in one sentence before editing. If the invariant cannot be stated precisely, stop at investigation and ask for clarification rather than combining several findings.

## Finding implementation template

Use this template in the working notes or task plan for every item:

```text
Security item: SEC-___ — <title>
Severity/status before work: <value from security-audit.md>
Threat: <attacker capability and protected asset>
Invariant: <one sentence that must always be true>
In scope: <exact files, route/job/service/config surface>
Out of scope: <related findings intentionally deferred>
Existing controls: <controls that must remain intact>
Implementation: <smallest safe design>
Regression tests: <success, denial, malformed, replay/duplicate, and boundary cases>
Deployment checks: <only when source tests cannot prove the control>
Ledger update: <status and evidence to record after verification>
```

## Implementation sequence

### 1. Establish the threat and invariant

Describe what the attacker can control, what asset or action is at risk, and what must be true after the change.

Examples:

- `SEC-003`: attacker controls the client filename; invariant: no client-provided path component reaches a filesystem operation.
- `SEC-009`: attacker controls the webhook body; invariant: only an authenticated, valid, non-replayed, allowlisted message can dispatch work.
- `SEC-007`: an archive may be copied or downloaded; invariant: archive contents are unreadable without the deployment recovery key and contain no plaintext recovery token.

### 2. Make the smallest boundary change

Prefer the existing Laravel boundary:

| Security surface | Preferred implementation boundary |
|------------------|------------------------------------|
| Authentication/session | Filament auth page, session config, middleware, password/OTP service |
| Household authorization | `HouseholdAccess`, `ExpensePolicy`, `BudgetPolicy`, `RecurringPolicy`, `RequiresPrimaryHouseholdAccess` |
| Webhook | Route middleware/Form Request/DTO, verifier service, queued job, idempotency store |
| Guest restore | `GuestRestoreBackupRequest`, `GuestRestoreBackupController`, `BackupService` |
| Backup | `BackupService`, `config/backup.php`, storage disk and retention configuration |
| File upload | Form Request/Filament upload configuration, private disk, controlled download |
| AI pipeline | Ollama service, prompt builder, normalizer, queued job, redacted logging |
| Operational disclosure | Health probe result/aggregator, error mapper, role-aware UI |
| Production baseline | `config/*`, deployment environment, reverse proxy, firewall, database transport |

Do not move domain side effects into a controller merely to fix a test. Do not make a security control depend only on client-side Livewire/Filament visibility; enforce it again on the server boundary.

### 3. Add regression coverage before declaring implementation complete

For every security change, cover the relevant combination of:

- authorized success;
- unauthorized or unauthenticated denial;
- malformed or oversized input;
- boundary values and path separators;
- duplicate, replayed, concurrent, or retried input;
- Primary versus Family Member behavior;
- secret/error/log redaction;
- external HTTP/process/storage fakes.

For webhook and backup work, use `Http::fake()`, `Queue::fake()`, `Storage::fake()`, and ZIP fixtures as appropriate. Never call real Evolution, Ollama, or a live backup store in automated tests.

For asynchronous work, prove the final persisted state and idempotency behavior. A queued job alone is not verification.

### 4. Run the smallest applicable verification

The exact command belongs in the handoff and the register row. Typical commands are:

```powershell
php artisan test --compact tests/Feature/RelevantSecurityTest.php
php artisan test --compact --filter=RelevantSecurityCase
composer audit --no-interaction
npm audit --omit=dev --audit-level=moderate
git diff --check
```

Run `vendor/bin/pint --dirty --format agent` only when PHP files changed. Run broader tests, Larastan, builds, or authenticated/browser/live integration checks when required by [.codex/VERIFICATION.md](../.codex/VERIFICATION.md) and the affected surface.

If a check is unavailable, record the exact command, error, and why the result is not evidence of safety. Do not replace a skipped security check with a weaker claim.

### 5. Review the security diff

Before updating the ledger:

- inspect the complete tracked and intended untracked diff;
- search for secrets, restore tokens, raw payload logging, raw exception responses, and unsafe client paths;
- confirm the change did not alter unrelated household authorization or backup behavior;
- verify relative Markdown links and anchors when docs changed;
- confirm no debug artifacts or temporary instrumentation remain;
- distinguish confirmed code evidence from deployment checks that still require an operator.

### 6. Update the register

For the selected row in [security-audit.md](security-audit.md):

- change `Open` to `In progress` when implementation begins;
- change to `Implemented` only when the scoped code/config change exists;
- add the branch, changed files, test commands, and skipped checks in the task handoff;
- change to `Verified` only after all applicable tests and deployment checks pass;
- use `Needs deployment verification` when the remaining control belongs to HTTPS, proxy, firewall, storage, database, secret, or service operations;
- use `Accepted risk` only after the project owner explicitly accepts the residual risk and the reason/review date is recorded.

Do not rewrite the original evidence to make a finding appear less serious. Add a dated verification note below the row or in the associated change summary.

## Finding routing map

Use this map to locate the implementation context without broadening the selected change:

| IDs | Primary docs and source context |
|-----|--------------------------------|
| `SEC-001`, `SEC-009`, `SEC-010`, `SEC-011`, `SEC-027` | [evolution-local-windows.md](evolution-local-windows.md), [household-access.md](household-access.md), `routes/api.php`, `WhatsAppWebhookController`, WhatsApp jobs, [WhatsAppWebhookTest.php](../tests/Feature/WhatsAppWebhookTest.php) |
| `SEC-003` through `SEC-008` | [backups-and-danger-zone.md](backups-and-danger-zone.md), `GuestRestoreBackupRequest`, `GuestRestoreBackupController`, `BackupService`, [GuestRestoreBackupTest.php](../tests/Feature/GuestRestoreBackupTest.php) |
| `SEC-002` | `composer.json`, `composer.lock`, installed package metadata, Composer audit output |
| `SEC-012`, `SEC-021`, `SEC-024`, `SEC-025` | `config/session.php`, `config/database.php`, `config/services.php`, `HorizonServiceProvider`, deployment/reverse-proxy documentation |
| `SEC-013`, `SEC-014`, `SEC-015` | `routes/web.php`, `ChangelogHelper`, ignored debug artifacts, public disclosure policy |
| `SEC-016`, `SEC-017`, `SEC-018`, `SEC-027` | [whatsapp-bot-commands.md](whatsapp-bot-commands.md), [whatsapp-manual-expense.md](whatsapp-manual-expense.md), media/manual jobs, `OllamaService`, receipt tests |
| `SEC-019`, `SEC-020`, `SEC-026` | [active-sessions.md](active-sessions.md), [household-access.md](household-access.md), Filament login/OTP/password-reset pages and tests |
| `SEC-022`, `SEC-023` | [service-status.md](service-status.md), health probes, models, resources, policies, and authorization tests |

## AI handoff format

Use this compact format when asking an AI agent to implement one item:

```text
Implement SEC-___ from docs/security-audit.md.

Read first:
- docs/security-audit.md (selected row)
- docs/security-hardening-playbook.md
- <linked domain docs>
- <linked source files and focused tests>

Keep in scope:
- <exact behavior and files>

Keep out of scope:
- <other SEC IDs>

Acceptance:
- <security invariant>
- <denial/boundary/replay/redaction behavior>
- <authorization behavior that must remain unchanged>

Verification:
- <exact focused test command>
- <manual/deployment check if source tests cannot prove it>

Do not commit, push, publish, or modify unrelated files without separate approval.
```

## Completion handoff format

Every completed item should report:

1. The selected `SEC-*` item and the invariant it now enforces.
2. Files changed and any intentionally untouched related paths.
3. Exact verification commands and results.
4. Deployment/manual checks performed, skipped, or still required.
5. Existing controls preserved and remaining residual risk.
6. The updated register status and the next unaddressed `SEC-*` item.

Commit, push, pull request, deployment, secret rotation, live WhatsApp/Evolution calls, and production changes remain separate approval steps.

## Public-release gate

Before public deployment, every Critical and High row must be `Verified` or have an explicit owner-approved exception. The following conditions must also be demonstrated in the target environment:

- production debug is disabled and error responses are generic;
- all secrets are strong, rotated, non-default, and absent from logs/docs/artifacts;
- HTTPS, secure cookies, security headers, trusted proxy/host settings, and database TLS are verified;
- webhook authentication, sender trust, request limits, replay protection, idempotency, queue limits, and async completion are verified;
- guest restore has safe filename/path handling, archive limits, integrity binding, atomic token use, and encrypted backup inputs;
- backups are encrypted, scoped, access-controlled, retained deliberately, and restore-tested;
- uploads and public storage cannot execute or expose arbitrary restored content;
- Composer and npm dependency audits are clean or have documented owner-approved exceptions;
- Horizon and Service Status provide enough protected operational visibility for incident response;
- focused security tests and the required broader verification matrix pass.

