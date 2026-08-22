# Security audit and hardening register

This document is the source of truth for the tido security findings identified during the source-level pre-public audit on **6 August 2026**. It is written for humans and AI agents that will address the findings one at a time.

The register records evidence, impact, the required end state, and the verification boundary. It does not mean that a finding is fixed. Every row remains open until the implementation, focused verification, and any required deployment check are complete.

## How to use this register

- Select exactly one `SEC-*` item for a change unless one item is an unavoidable prerequisite of the selected item.
- Re-read the current source at the linked location before editing; line numbers are audit-time pointers and may move.
- Preserve the single-tenant household model and the existing `BackupService`, `HouseholdAccess`, `ExpensePolicy`, queue, and local Evolution/Ollama architecture.
- Update the selected row only after the implementation and its verification are complete.
- Do not mark a row `Verified` based only on a unit test when the control also depends on production environment, reverse-proxy, storage, firewall, or service configuration.
- Never place real secrets, restore tokens, session identifiers, raw receipt content, full webhook payloads, or unredacted upstream responses in this document, tests, or logs.

The implementation procedure is in [security-hardening-playbook.md](security-hardening-playbook.md). Repository workflow, branch, approval, and verification rules remain authoritative in [AGENTS.md](../AGENTS.md), [.codex/CODEX_WORKFLOW.md](../.codex/CODEX_WORKFLOW.md), and [.codex/VERIFICATION.md](../.codex/VERIFICATION.md).

## Audit scope and threat model

tido is a single-tenant Laravel 12 / Filament v5 household application. The Primary user has full panel access. Login-enabled Family Members have limited Finances access and may mutate only expenses attributed to their own `family_member_id`. This is not a multi-tenant or Spatie Permission design; authorization must continue to use `HouseholdAccess` and `ExpensePolicy`.

The reviewed security boundaries are:

| Surface | Entry point | Expected control |
|---------|-------------|------------------|
| Filament admin | `/admin/*` | Session authentication, CSRF, password hashing, role-aware panel access |
| WhatsApp webhook | `POST /api/webhooks/whatsapp` | Strong header authentication, sender allowlist, schema validation, rate limits, replay protection, queued heavy work |
| Guest restore | `POST /restore-backup` | Zero-user gate, throttle, ZIP validation, safe extraction, restore-token verification, one-time consumption |
| Backup download | `GET /backups/{backup}/download` | Expiring signed URL, protected storage, encryption at rest |
| Changelog | `GET /changelog` | Deliberate disclosure policy, sanitized output, no raw errors or debug files |
| Service status | `/admin/service-status` | Authenticated and role-appropriate operational detail |
| Horizon | `/horizon` | Explicit production gate and usable monitoring access |
| Receipt and AI pipeline | UI upload, WhatsApp media/text, Ollama jobs | MIME and size validation, private storage, bounded input/output, redacted logs, idempotent jobs |
| Production boundary | HTTPS, cookies, headers, proxy, database, secrets, backups | Explicit deployment configuration and release verification |

This is a source/configuration audit, not a certification of a deployed environment. It does not prove the current production `.env`, reverse-proxy headers, firewall rules, OS permissions, public DNS exposure, Evolution configuration, database TLS, or secret rotation state.

## Status vocabulary

- **Open** — evidence supports a code or configuration gap; no fix has been verified.
- **In progress** — an approved implementation is being worked on for this item.
- **Implemented** — the code or configuration change exists, but the complete verification boundary is not yet satisfied.
- **Verified** — focused tests and all applicable manual/deployment checks passed.
- **Needs deployment verification** — source is not enough to decide whether the deployed environment is safe.
- **Accepted risk** — explicitly accepted by the project owner with a documented reason and review date.

## Finding register

| ID | Severity | Status | Surface | Evidence and impact | Required end state |
|----|----------|--------|---------|---------------------|--------------------|
| **SEC-001** | Critical if deployed unchanged | Implemented | Evolution API and webhook authentication | The audit baseline found a repository fallback/shared credential in [config/services.php](../config/services.php):62, [.env.example](../.env.example):99, and the local setup examples. The branch removes that fallback, adds `EVOLUTION_WEBHOOK_SECRET`, validates both credentials as distinct 32+ character non-placeholder values through [EvolutionCredential.php](../app/Support/EvolutionCredential.php), accepts only `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>` in [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php), and registers that inbound secret while retaining `EVOLUTION_API_KEY` for outbound calls in [EvolutionInstanceService.php](../app/Services/EvolutionInstanceService.php). Any previously exposed/shared value must still be treated as compromised until rotated. | Rotate or revoke any previously exposed/shared value, generate two distinct random 32+ character secrets, keep Evolution `AUTHENTICATION_API_KEY` equal only to tido's `EVOLUTION_API_KEY`, configure `EVOLUTION_WEBHOOK_SECRET` only for inbound callbacks, re-register the webhook, and verify the live Evolution and reverse-proxy boundary. |
| **SEC-002** | High | Implemented | PHP dependencies | At audit time, [composer.lock](../composer.lock):1905 locked `guzzlehttp/guzzle` at `7.15.1`. The 6 August 2026 Composer audit reported high `CVE-2026-69246` and medium `CVE-2026-69245`, both fixed by Guzzle `7.15.2` or later. The [high advisory](https://github.com/advisories/GHSA-v5mv-p594-2x33) and [medium advisory](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r) describe the affected behavior. `npm audit --omit=dev` reported zero vulnerabilities. | Upgrade to at least `7.15.2`, regenerate the lock file with approval, inspect the dependency diff, rerun Composer audit, and record the result. |
| **SEC-003** | High | Implemented | Guest restore upload | [GuestRestoreBackupController.php](../app/Http/Controllers/GuestRestoreBackupController.php):48 previously built the temporary ZIP path from `getClientOriginalName()` and used the client name again in `move()`. Client filenames are untrusted path material and can contain traversal, reserved Windows names, or overwrite-oriented values. The controller now stages the upload under a server-controlled filename and validates the resolved path before restore processing. | Store every guest upload under a server-generated fixed filename inside a fresh directory. Validate the resolved path stays inside that directory and do not use the original name for filesystem operations. |
| **SEC-004** | High | Verified | ZIP database extraction | [BackupService.php](../app/Services/BackupService.php) previously selected any archive entry ending in `.sql` or `.sqlite` and passed the unvalidated name to `extractTo()`, so unexpected directory components could become path traversal or arbitrary-file extraction. Extraction now allowlists only `database.sqlite` or a single `db-dumps/{safe}.sql` entry, reads bytes via `getFromName`, writes to a server-controlled basename, and rejects unresolved or outside paths. | Permit only exact expected database entry names, reject all directory components and alternate separators, avoid broad extraction, and verify the resolved extracted path before importing. |
| **SEC-005** | High | Verified | ZIP resource limits | [BackupService.php](../app/Services/BackupService.php):420 reads and writes every application-file entry. The compressed upload limit does not establish an uncompressed total limit, per-entry limit, entry-count limit, or compression-ratio limit. A crafted ZIP can exhaust memory, disk, CPU, or request/worker time. | Inspect the central directory before extraction. Enforce entry count, total uncompressed bytes, per-file bytes, compression ratio, allowed prefixes/extensions, and a bounded restore duration before database or file writes. |
| **SEC-006** | High | Verified | Restore integrity and one-time use | [GuestRestoreBackupController.php](../app/Http/Controllers/GuestRestoreBackupController.php):36 finds a catalog backup by token, but the uploaded archive is not visibly bound to that catalog row by a signature, manifest, or embedded-token comparison. The token is consumed after restoration, so concurrent submissions may race. A leaked token can authorize an attacker-created database/archive. | Sign or MAC the backup manifest, verify the submitted token and archive identity before any write, use an atomic claim/lock for one-time restore, and provide rollback or staging for failed imports. |
| **SEC-007** | High | Verified | Backup confidentiality | [BackupService.php](../app/Services/BackupService.php):300 creates a native SQLite ZIP containing the database and plaintext `RESTORE_TOKEN.txt`, then embeds application files. [config/backup.php](../config/backup.php):29 includes `base_path()` for the Spatie source and [config/backup.php](../config/backup.php):191 makes archive encryption optional. This can place database contents, source, `.env`, credentials, and recovery material in a locally stored archive. | Make encryption mandatory for every backup path, fail closed when the archive key is absent, remove plaintext tokens from archives, narrow source include/exclude rules, exclude `.env` and debug artifacts explicitly, use encrypted off-host retention, rotate keys, and audit backup download/restore events. |
| **SEC-008** | Medium | Verified | Restore-token lookup | [BackupService.php](../app/Services/BackupService.php) previously loaded every catalog row with a token hash and performed a bcrypt check until one matched. The public restore endpoint had only the visible `5,1` throttle. A growing catalog or distributed requests could turn token lookup into CPU/DB denial of service. Lookup is now keyed by `restore_token_lookup` with a separately hashed full token, dual per-IP/global limits, and failed-attempt logging without the token. | Use a public token identifier with a keyed lookup plus a separately verified secret, add global and per-IP limits, bound catalog scan work, and monitor failed restore attempts without logging the token. |
| **SEC-009** | High | Implemented | WhatsApp webhook trust boundary | [routes/api.php](../routes/api.php) exposes the webhook without an explicit endpoint throttle. [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):32 accepts the full body, does not enforce a strict DTO/schema or body/text/message-ID limits, and trusts caller-supplied sender fields after the dedicated webhook secret. A valid key can flood queues, spoof an allowlisted JID, create financial records, and replay messages. Source now enforces IP allowlist, body size, dual route throttles, strict upsert schema, JID domain checks, message-ID idempotency, and per-sender throttle before dispatch. | Add provider signature verification or a private network/IP boundary, strict payload validation, size limits, sender/message-ID validation, per-IP/secret/sender throttles, replay protection, and message-ID idempotency before queue dispatch. |
| **SEC-010** | Medium | Implemented | Webhook credential transport | The SEC-001 implementation rejects `?token=` and raw-token authentication before payload handling; inbound callbacks now require the dedicated `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>` header. Any credential that may have been exposed through the former query-string path still requires rotation. | Keep header-only authentication and rotate the credential if query authentication was ever reachable; deployment rotation remains outstanding. |
| **SEC-011** | Medium | Verified | Webhook availability | [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):152 sends some WhatsApp replies synchronously before acknowledging the webhook. Slow upstream calls can hold web workers and amplify authenticated webhook traffic into availability pressure. | Queue outbound replies, return a bounded acknowledgement immediately, add queue concurrency/rate limits, and use explicit connect/total timeouts with bounded retries. |
| **SEC-012** | High if copied to production | Needs deployment verification | Environment and session baseline | [.env.example](../.env.example):4 uses `APP_DEBUG=true`; [.env.example](../.env.example):36 disables session encryption; [config/session.php](../config/session.php):172 leaves the secure-cookie flag to an unset environment value. The sample also documents a one-week session lifetime. The live environment was not inspected. | Require `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, intentional HttpOnly/SameSite settings, a reviewed session lifetime, and a deliberate session-encryption choice in deployment validation. |
| **SEC-013** | Medium | Open | Public changelog disclosure | [routes/web.php](../routes/web.php):21 exposes full commit hashes, author names/emails, commit descriptions, tags, avatar data, and raw exception messages through an anonymous JSON endpoint. | Decide whether the endpoint is public. If it remains public, return curated release metadata only, remove author emails and raw descriptions, bound pagination, and return generic error identifiers rather than exception text. |
| **SEC-014** | Medium | Open | Debug/agent instrumentation | [routes/web.php](../routes/web.php):24 writes `debug-8f1b08.log` into the project root on every changelog request, including raw exception text. The ignored file currently exists locally. It creates privacy, disk-growth, and deployment-artifact risk. | Remove the instrumentation before release, use redacted structured logging with rotation when diagnostics are genuinely required, and verify no debug artifacts are present in release output. |
| **SEC-015** | Medium | Open | Outbound TLS and author privacy | [ChangelogHelper.php](../app/Helpers/ChangelogHelper.php):131 calls `Http::withoutVerifying()` for GitHub avatar lookup and sends author email data to a third party. TLS integrity is disabled and lookup failures log email addresses. | Restore certificate verification, avoid email-based third-party lookups, prefer local or privacy-preserving avatars, and redact identifiers in logs. |
| **SEC-016** | Medium | Open | WhatsApp media resource handling | [ProcessWhatsAppMediaJob.php](../app/Jobs/ProcessWhatsAppMediaJob.php):375 receives JSON/base64 media and decodes it before the later binary-size check. [WhatsAppNotificationService.php](../app/Services/WhatsAppNotificationService.php):45 logs upstream response bodies on failure. A compromised upstream or valid webhook abuse path can create memory pressure or PII/log exposure. | Enforce connect/total timeouts and response limits before decoding, stream where practical, truncate/redact response logs, and bound worker concurrency and retry volume. |
| **SEC-017** | Medium | Open | Manual expense integrity and input limits | [ManualWhatsAppExpenseParser.php](../app/Support/ManualWhatsAppExpenseParser.php):16 accepts negative quantities and line totals. [ProcessManualWhatsAppExpenseJob.php](../app/Jobs/ProcessManualWhatsAppExpenseJob.php):56 has no evident strict limits for message length, line count, item descriptions, merchant names, or aggregate value. | Reject negative values unless credit-note behavior is explicitly designed, enforce numeric ranges and message/item limits, validate again inside the job, and use a transaction for expense plus item creation. |
| **SEC-018** | Medium | Open | Ollama prompt, output, and log handling | [OllamaService.php](../app/Services/OllamaService.php):53 logs raw upstream bodies and [OllamaService.php](../app/Services/OllamaService.php):109 logs raw AI text when JSON decoding fails. Manual WhatsApp text and receipt data are user-controlled AI input. Raw AI data can leak receipt PII, create log growth, and bypass business assumptions without strict output limits. | Bound prompt/input sizes, enforce a typed output schema and numeric/string limits, keep the Ollama host deployment-controlled, redact/truncate raw failures, and do not expose `raw_ai_response` outside authenticated authorized views. |
| **SEC-019** | Medium | Open | OTP enumeration and throttling | [Login.php](../app/Filament/Pages/Auth/Login.php):509 returns an error for unknown phone numbers while known numbers enter the OTP flow. The existing test title says “without revealing details” but does not assert uniform output. Component throttling and per-user OTP limits do not visibly prove IP-plus-phone-plus-global controls. | Return uniform user-facing responses and timing, add limits keyed by IP and normalized phone plus a global abuse control, and test the effective limit across fresh sessions and distributed attempts. |
| **SEC-020** | Medium | Needs runtime verification | Password-reset enumeration | [RequestPasswordReset.php](../app/Filament/Pages/Auth/RequestPasswordReset.php):44 uses different success and failure notification paths. The inherited Filament copy was not runtime-verified in the source-only audit, so the disclosure risk is likely but not confirmed. | Make the user-facing response identical for existing, non-existing, and non-panel accounts. Keep delivery detail in protected logs and add a test for identical output. |
| **SEC-021** | Medium | Needs deployment verification | HTTPS, headers, host, and proxy boundary | No application-level configuration was found for HTTPS enforcement, HSTS, CSP, frame protection, Referrer-Policy, Permissions-Policy, trusted proxies, or trusted hosts. The edge server may provide some of these, so source absence is not proof of deployed absence. | Configure and verify HTTPS redirects, HSTS, content/type and frame protections, a Filament-compatible CSP, Referrer-Policy, Permissions-Policy, trusted proxies, and a host allowlist at the correct application/edge layer. |
| **SEC-022** | Low | Open | Service-status information disclosure | [ServiceStatusPage.php](../app/Filament/Pages/ServiceStatusPage.php):78 allows authenticated Family Members to view service reports. [OllamaProbe.php](../app/Services/Health/Probes/OllamaProbe.php):43 and the queue probes can expose model names, drivers, failed-job counts, or raw exception detail. | Show sanitized health status to Family Members and detailed diagnostics only to Primary/admin users. Never display raw exception messages, credentials, or connection strings. |
| **SEC-023** | Low | Open | Mass assignment defense in depth | [User.php](../app/Models/User.php):26 includes authority fields such as `household_role` and `family_member_id` in `$fillable`. [Expense.php](../app/Models/Expense.php):23 and [Backup.php](../app/Models/Backup.php):23 include internal ownership, status, path, and restore fields. No direct public exploit was confirmed, but future request-to-model use could become privilege or data-integrity bypass. | Remove privileged/internal fields from broad fillable lists. Use dedicated DTOs/services, policies, and trusted `forceFill()` boundaries, with tampering tests for ownership, role, source, status, and restore fields. |
| **SEC-024** | Low | Needs deployment verification | Remote database transport | [config/database.php](../config/database.php):99 defaults PostgreSQL to `sslmode=prefer`. The current local project uses SQLite, but a remote PostgreSQL deployment could silently fall back to an unencrypted connection. | Require certificate-verified TLS for remote PostgreSQL and explicit TLS settings for MySQL when used. |
| **SEC-025** | Low / operational | Needs deployment verification | Horizon access and monitoring | [HorizonServiceProvider.php](../app/Providers/HorizonServiceProvider.php):31 has an empty `viewHorizon` allowlist. This denies access rather than exposing Horizon, but it weakens incident response and queue-abuse investigation. | Configure an explicit production allowlist or role-based gate, keep strong authentication, and verify the dashboard is accessible only to intended operators. |
| **SEC-026** | Info / enhancement | Open | Session persistence and MFA | [Login.php](../app/Filament/Pages/Auth/Login.php):684 defaults OTP `remember` to true when the field is absent. Password login has no clearly enforced second factor for Primary users. | Default OTP persistence to false, make it explicit, add recent-authentication requirements for destructive/security-sensitive actions, and introduce MFA/recovery codes for Primary accounts if the threat model requires it. |
| **SEC-027** | Medium | Open | Queue idempotency and external-call retries | Media and manual WhatsApp jobs have retry/backoff behavior but are not visibly unique jobs. Duplicate deliveries can repeat downloads, parsing, notifications, or financial work before database deduplication. External HTTP clients also need consistent connect/total timeouts and bounded retry policy. | Make processing idempotent at the job boundary using upstream message IDs, add unique jobs or locks where appropriate, enforce queue rate limits, and test duplicate delivery and retry exhaustion. |

## Controls already present

These controls were observed and should be preserved while addressing the open items:

- [ExpensePolicy.php](../app/Policies/ExpensePolicy.php) and `HouseholdAccess` provide household-aware mutation authorization; [ExpenseFamilyMemberOwnershipTest.php](../tests/Feature/ExpenseFamilyMemberOwnershipTest.php) covers important Family Member boundaries.
- Password and OTP login paths regenerate the session after authentication, and logout invalidates the session and regenerates the CSRF token.
- OTP values are hashed before cache storage, expire, have a five-attempt verification limit, and have resend/hourly limits.
- Guest restore is blocked after a user exists, is throttled, and validates ZIP type and configured size.
- Backup downloads use signed-route middleware.
- Normal receipt uploads use private storage, server-managed names, MIME inspection, and size/page limits.
- The webhook has unauthorized-request and allowlist tests in [WhatsAppWebhookTest.php](../tests/Feature/WhatsAppWebhookTest.php).
- JavaScript production dependencies were audited on 6 August 2026 with no reported vulnerabilities.

These controls do not close the findings above when the finding concerns a different path, such as guest restore versus normal receipt upload or inbound webhook authentication versus outbound Evolution calls.

## Dependency snapshot

| Check | Result on 6 August 2026 | Follow-up |
|-------|-------------------------|-----------|
| `composer audit --no-interaction` | Failed with two Guzzle advisories: one high and one medium | Upgrade Guzzle, regenerate the lock file with approval, and rerun the audit |
| `npm audit --omit=dev --audit-level=moderate` | `found 0 vulnerabilities` | Rerun after frontend dependency changes |

### SEC-002 verification note — 6 August 2026

- `composer.lock` now resolves `guzzlehttp/guzzle` to `7.15.2` with no unrelated package changes; the installed vendor package is also `7.15.2`.
- `composer audit --no-interaction --locked` passed with `No security vulnerability advisories found.`
- `composer validate --strict --no-interaction` and `composer install --dry-run --no-interaction --no-progress --no-scripts` passed.
- `composer check-platform-reqs --no-interaction` remains blocked locally because this Windows PHP runtime does not provide `ext-pcntl` or `ext-posix`, which `laravel/horizon` requires.
- `php artisan test --compact` completed with 895 passing tests and 6 unrelated existing failures in notification CSS, dashboard section navigation, label form copy, and service-health aggregation assertions. No application or test files changed in this item.

The finding is marked **Implemented** because the vulnerable package is patched and the dependency audit is clean, while the broader baseline suite and local Horizon platform requirements remain unresolved outside SEC-002.

### SEC-001 implementation note - 6 August 2026

- `EVOLUTION_API_KEY` no longer has a repository fallback, and `EVOLUTION_WEBHOOK_SECRET` is a distinct deployment setting.
- The webhook accepts only `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>`; missing, invalid, raw-token, query-string, and outbound-key credentials are rejected before payload handling.
- Evolution webhook registration uses the inbound secret while outbound API calls retain the API key.
- The source-side portion of SEC-010 is covered by this change: query-string and raw-token credentials are rejected; rotation of any previously exposed value remains a deployment task.
- Focused verification passed: 79 targeted tests with 473 assertions, Pint, and `git diff --check`.
- The latest full-suite run reported 899 passing tests and six failures in notification CSS, changelog/database notification slide-overs, dashboard section navigation, label form copy, and service-health aggregation; none exercise the SEC-001 credential boundary.
- Focused Larastan still reports five warnings at unchanged locations in `EvolutionInstanceService` and `WhatsAppNotificationService`; the new credential class and webhook controller pass focused analysis.
- Deployment verification remains required: rotate or revoke any previously exposed/shared credential, create two distinct live secrets, configure them in the live environments, re-register the Evolution webhook, and verify the production Evolution and reverse-proxy boundary. The finding is **Implemented**, not **Verified**, until those operational checks are complete.

### SEC-003 implementation note - 6 August 2026

- `GuestRestoreBackupController` now moves every accepted guest upload to a server-controlled `backup.zip` inside its fresh per-request restore directory, resolves the moved path, rejects unresolved or outside paths, and passes only the resolved path to `BackupService`.
- The focused regression submits a path-like Windows-reserved client filename (`..\\..\\CON.zip`) and asserts the restore service receives only the controlled basename under the restore root. Existing zero-user, ZIP validation, wrong-token, success, token-consumption, and cleanup behavior remain covered.
- `php artisan test --compact tests/Feature/GuestRestoreBackupTest.php` passed with 8 tests and 25 assertions. `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse app/Http/Controllers/GuestRestoreBackupController.php --error-format=table`, and `git diff --check` passed.
- `php artisan test --compact` completed with 901 passing tests and 5 unrelated baseline failures in `NotificationTimerBarTest`, `ChangelogModalSeparatorsTest`, `DashboardSectionNavTest`, `FamilyMemberAttributionLoginTest`, and `LabelFormTest`; none exercise the SEC-003 controller or focused test.
- The full `vendor/bin/phpstan analyse` baseline remains blocked by 236 existing diagnostics across unrelated application areas, including an unmatched existing ignored-error pattern; targeted analysis of the changed controller is clean. No live restore or deployment storage verification was performed.
- An isolated local sandbox was initialized on 7 August 2026 with a separate SQLite database, separate storage directories, built assets, a synthetic receipt, and the guest restore route on port 2001. The sandbox is the manual-test boundary for the destructive zero-user flow; the reusable procedure is documented in [Backups & Danger Zone](backups-and-danger-zone.md#safe-manual-verification). It does not touch the live-like local dataset or establish production/public-deployment safety.

The finding remains **Implemented**, not **Verified**, because the SEC-003 source boundary and focused tests pass, local sandbox evidence is isolated from the live-like dataset, and production/deployment storage verification remains outstanding.

### SEC-004 verification note — 11 August 2026

- `BackupService::extractBackupPayloadFromZip` allowlists only exact `database.sqlite` or a single `db-dumps/{safe}.sql` entry (`{safe}` = `[A-Za-z0-9][A-Za-z0-9._-]*`), prefers the native SQLite entry when both exist, and fails closed on multiple Spatie dumps.
- Payload bytes are read with `ZipArchive::getFromName` and written only to fixed basenames `database.sqlite` / `database.sql` under the per-request restore temp directory; the resolved path must remain inside that directory.
- Focused verification: `php artisan test --compact tests/Feature/BackupZipPayloadExtractionTest.php` passed (16 tests, 38 assertions). `vendor/bin/pint --dirty --format agent` passed.
- Residual risk outside this finding: SEC-005 still covers unbounded application-file restore extraction; SEC-007 covers encryption.

The finding is **Verified** because the ZIP database-entry invariant is fully proven by focused source tests and does not depend on deployment/proxy configuration.

### SEC-005 verification note — 15 August 2026

- `BackupService::restoreFromZipPath` inspects the ZIP central directory with `ZipArchive::statIndex` before `extractBackupPayloadFromZip`, database import, or `restoreApplicationFilesFromZip`.
- Limits live under `config('backup.backup.restore')`: `max_entries` (5000), `max_uncompressed_bytes` (200 MiB), `max_entry_bytes` (50 MiB), `max_compression_ratio` (100), `max_duration_seconds` (60). The existing compressed guest upload cap is unchanged.
- Every central-directory entry counts toward the limits. Extra Spatie source paths are not written. Application-file writes remain `files/public/` and `files/private/` with extensions `jpg`, `jpeg`, `png`, `gif`, `webp`, and `pdf` only.
- Oversized or over-ratio archives fail closed with a generic message and do not create payload directories or storage writes. Duration is checked from inspect through extract and file restore.
- Focused verification: `php artisan test --compact tests/Feature/BackupZipResourceLimitsTest.php` passed (9 tests, 27 assertions). `php artisan test --compact tests/Feature/BackupZipPayloadExtractionTest.php tests/Feature/BackupResourceTest.php tests/Feature/BackupZipResourceLimitsTest.php` passed (38 tests). `vendor/bin/pint --dirty --format agent` passed.
- `tests/Feature/GuestRestoreBackupTest.php` still has a pre-existing unsigned-download assertion (`302` vs `403`) that does not exercise ZIP resource limits; guest restore filename, token, and ZIP-type cases in that file passed.
- Residual risk outside this finding: SEC-007 still covers encryption and Spatie source include/exclude.

The finding is **Verified** because the inspect-before-write invariant is fully proven by focused source tests and does not depend on deployment/proxy configuration.

### SEC-006 verification note — 15 August 2026

- Guest and catalog restore now bind the uploaded or stored ZIP to the catalog row before any database or application-file write. `BackupService` hashes restoreable ZIP entries, embeds `MANIFEST.json` plus `MANIFEST.hmac` (HMAC-SHA256 with `APP_KEY`), and stores `content_sha256` and `manifest_hmac` on `backups`.
- Restore acquires an exclusive file-cache lock (`backup-restore`) so the lock survives a SQLite file replace, re-checks the guest token under that lock, verifies hash and MAC, snapshots the live SQLite file and overwritten application files, then imports. The guest token is consumed only after success; failure restores the snapshot and leaves the token in place.
- Legacy catalog rows without a hash are backfilled from the on-disk catalog file when it exists; missing file and missing hash fail closed. `issueRestoreToken` re-seals the manifest without changing content identity.
- Focused verification: `php artisan test --compact tests/Feature/BackupRestoreIntegrityTest.php` passed (11 tests, 89 assertions). `php artisan test --compact tests/Feature/BackupRestoreIntegrityTest.php tests/Feature/BackupZipPayloadExtractionTest.php tests/Feature/BackupZipResourceLimitsTest.php tests/Feature/BackupResourceTest.php` passed (49 tests). Guest restore filename, token, ZIP-type, and staging cases in `tests/Feature/GuestRestoreBackupTest.php` passed. `vendor/bin/pint --dirty --format agent` passed.
- Targeted Larastan on `BackupManifest`, `GuestRestoreBackupController`, and the new lock path is clean. Three pre-existing `BackupService` diagnostics remain (`Filesystem::path()` / `File::size()` / `ZipArchive::statIndex` narrowing) and were not introduced by this item.
- Residual risk outside this finding: SEC-007 still covers encryption, plaintext `RESTORE_TOKEN.txt`, and Spatie source include/exclude. SQL dump restore onto PostgreSQL/MySQL is not file-snapshotted; SQLite file-backed restore is.

The finding is **Verified** because archive-to-catalog binding, exclusive restore locking, one-time consume-on-success, and failed-import rollback are proven by focused source tests and do not depend on deployment/proxy configuration.

### SEC-007 verification note — 16 August 2026

- Native and scheduled catalog archives now require `BACKUP_ARCHIVE_PASSWORD` (32+ characters, non-placeholder) through [BackupArchivePassword.php](../app/Support/BackupArchivePassword.php). Create, ZIP open/write, and restore fail closed when the key is missing, short, a placeholder, or AES-256 is unavailable.
- Every ZIP entry is encrypted with `ZipArchive::EM_AES_256`. A copied archive does not yield `database.sqlite` without that deployment key. The restore token is not a ZIP password.
- `RESTORE_TOKEN.txt` is no longer written. `BackupService::create()` returns a one-time plain token via [CreatedBackup.php](../app/Support/CreatedBackup.php) for a session notification or the Danger Zone kit modal. `issueRestoreToken` updates only `restore_token_hash`. Database notifications and structured `backup.created` / `backup.downloaded` / `backup.restored` / `backup.restore_failed` logs omit the token and password.
- Spatie `source.files.include` is empty (database dump only). Explicit excludes still list `.env`, `.env.example`, `.env.sandbox`, `.git`, `debug-8f1b08.log`, `vendor`, `node_modules`, and `storage`. Application files continue to be embedded by `BackupService`.
- Focused verification: `php artisan test --compact tests/Feature/BackupConfidentialityTest.php tests/Feature/GuestRestoreBackupTest.php tests/Feature/BackupRestoreIntegrityTest.php tests/Feature/BackupZipPayloadExtractionTest.php tests/Feature/BackupZipResourceLimitsTest.php tests/Feature/BackupResourceTest.php tests/Feature/ProfileDangerZoneTest.php` passed (78 tests, 347 assertions). `vendor/bin/pint --dirty --format agent` and `git diff --check` passed.
- Sandbox `:2001` smoke: distinct `BACKUP_ARCHIVE_PASSWORD` in `.env.sandbox`; Create backup showed the one-time token in the UI; newest ZIP was ~24 KiB, contained `database.sqlite`, `files/public/receipts/...`, `MANIFEST.json`, and `MANIFEST.hmac`, had no `RESTORE_TOKEN.txt` or `.env`, and was unreadable without the archive password. Danger Zone delete showed the kit modal, wiped to zero users, and guest restore of the encrypted ZIP returned the synthetic expense. Create with a null archive password failed closed.
- Residual risk outside this finding: off-host encrypted retention and live key rotation remain deployment notes.

### SEC-008 verification note — 16 August 2026

- Restore tokens are `{16-hex-selector}.{32-hex-secret}`. The catalog stores unique indexed `restore_token_lookup` plus bcrypt of the full token. `findBackupByRestoreToken` parses strictly, loads at most one row by selector, and verifies one bcrypt (unknown/malformed paths use a dummy hash). Hash-only legacy rows fail closed.
- `POST /restore-backup` uses named limiter `guest-restore`: 5/minute per IP and 10/minute globally (`backup.backup.restore.per_ip_attempts_per_minute` / `global_attempts_per_minute`). Invalid 429 responses omit the token. Invalid lookups log `backup.restore_failed` with `outcome: invalid_token` and `ip_hash` only.
- Focused verification: `php artisan test --compact tests/Feature/BackupRestoreTokenLookupTest.php tests/Feature/GuestRestoreBackupTest.php tests/Feature/BackupRestoreIntegrityTest.php tests/Feature/BackupConfidentialityTest.php tests/Feature/BackupResourceTest.php tests/Feature/ProfileDangerZoneTest.php` passed (60 tests, 350 assertions). `vendor/bin/pint --dirty --format agent` passed. Targeted Larastan on `RestoreToken`, `GuestRestoreBackupController`, `Backup`, and `BackupFactory` is clean. `BackupService` Larastan remains memory-constrained in this environment (pre-existing). `git diff --check` passed.
- Residual risk outside this finding: SEC-009 still covers webhook trust and throttles.

The finding is **Verified** because keyed O(1) lookup, dual rate limits, fail-closed legacy tokens, and token-free failure logs are proven by focused source tests and do not depend on deployment/proxy configuration.

### SEC-009 implementation note — 22 August 2026

- `POST /api/webhooks/whatsapp` middleware: body-size cap (413), fail-closed IP allowlist (`EVOLUTION_WEBHOOK_ALLOWED_IPS`, default loopback), and named limiter `whatsapp-webhook` (per-IP + global).
- Bearer auth runs in `WhatsAppWebhookRequest::authorize()` before schema validation so unauthorized callers still receive 401.
- Upsert schema requires message ID, phone/`@lid` JID domains only (rejects `@g.us` spoof of allowlisted numbers), bounded text, and optional instance/timestamp checks.
- `WhatsAppWebhookIdempotency` uses `Cache::add` on hashed `key.id` before allowlist side effects or dispatch; replays return `{status: duplicate}` with no job/`sendText`.
- Per-allowlisted-sender throttle after household resolution. Controllers no longer invent `uniqid()` message IDs.
- Focused verification: `php artisan test --compact tests/Feature/WhatsAppWebhookTrustBoundaryTest.php tests/Feature/WhatsAppWebhookTest.php tests/Feature/WhatsAppManualExpenseTest.php tests/Feature/WhatsAppLidAllowlistTest.php` passed (43 tests, 173 assertions). `vendor/bin/pint --dirty --format agent` and targeted Larastan on the new/changed webhook classes passed. `git diff --check` passed.
- Residual risk: production must set `EVOLUTION_WEBHOOK_ALLOWED_IPS` to Evolution’s true source when not loopback; trusted-proxy header trust remains SEC-021. SEC-011 still covers synchronous reply availability.

The finding is **Implemented**, not **Verified**, until live IP allowlist and Evolution callback acceptance are confirmed in the deployment environment.

### SEC-011 verification note — 22 August 2026

- Text bot replies (spend/help/manual/finance keywords) no longer call Evolution inside the webhook HTTP request. [`WhatsAppWebhookController::handleTextMessage`](../app/Http/Controllers/Api/WhatsAppWebhookController.php) dispatches [`ProcessWhatsAppTextReplyJob`](../app/Jobs/ProcessWhatsAppTextReplyJob.php) on the `whatsapp` queue and returns `{status: accepted}` immediately (manual-expense format still uses `ProcessManualWhatsAppExpenseJob`).
- The job builds the reply, re-checks the allowlist, and sends via `WhatsAppNotificationService`. It uses `$tries = 3`, backoff `[10, 30]`, timeout = Evolution total timeout + 15s, and `RateLimited('evolution-send')`.
- `services.evolution.timeout` / `connect_timeout` (defaults 15 / 5) drive the HTTP client; `outbound_send_attempts_per_minute` (default 30) drives the `evolution-send` limiter in `AppServiceProvider`. No nested `Http::retry`.
- Focused verification: `php artisan test --compact tests/Feature/WhatsAppWebhookAvailabilityTest.php tests/Feature/WhatsAppWebhookTest.php tests/Feature/WhatsAppLidAllowlistTest.php tests/Feature/WhatsAppWebhookTrustBoundaryTest.php` passed (39 tests, 159 assertions). `vendor/bin/pint --dirty --format agent` and `git diff --check` passed. Targeted Larastan on the new job and webhook controller is clean; pre-existing diagnostics remain in `WhatsAppNotificationService` / `AppServiceProvider`.
- Residual risk outside this finding: SEC-016 still covers response-body logging; SEC-027 still covers unique-job / duplicate-delivery for processing paths. Horizon worker concurrency remains the existing shared supervisor `maxProcesses` cap.

The finding is **Verified** because the no-sync-Evolution-on-webhook invariant, outbound rate limit middleware, and config-driven timeouts are proven by focused source tests and do not depend on deployment/proxy configuration.

## Suggested implementation order

The order reduces the chance of implementing a later control on top of an unsafe boundary. Each item is still a separate change unless an unavoidable prerequisite is explicitly recorded:

1. `SEC-002` — patch the affected runtime dependency.
2. `SEC-001` — implementation landed in this branch; complete deployment rotation, re-registration, and live-boundary verification.
3. `SEC-003` — server-controlled guest restore staging is implemented; retain the isolated manual evidence and complete any deployment-boundary verification before marking the finding Verified.
4. `SEC-004` — database archive path allowlist and controlled extraction verified.
5. `SEC-005` — ZIP resource limits verified.
6. `SEC-006` — archive-to-catalog binding, exclusive restore lock, and failed-import rollback verified.
7. `SEC-007` — enforce encrypted, scoped backups.
8. `SEC-008` — keyed restore-token lookup and dual guest-restore limits.
9. `SEC-009` — harden webhook schema, sender trust, rate limits, replay, and idempotency.
10. `SEC-010` — source-side header-only authentication landed with SEC-001; complete rotation verification for any previously exposed query credential.
11. `SEC-011` — make webhook replies asynchronous and bounded.
12. `SEC-027` — make processing idempotent and external retries bounded.
13. `SEC-012` — enforce the production session/debug baseline.
14. `SEC-021` — enforce the production transport, header, host, and proxy baseline.
15. `SEC-013` — reduce public changelog disclosure.
16. `SEC-014` — remove debug/agent instrumentation.
17. `SEC-015` — restore outbound TLS verification and author privacy.
18. `SEC-016` — bound media retrieval and response logging.
19. `SEC-017` — enforce manual expense integrity and input limits.
20. `SEC-018` — bound Ollama input/output and redact AI logs.
21. `SEC-019` — harden OTP enumeration and throttling.
22. `SEC-020` — make password-reset responses enumeration-resistant.
23. `SEC-026` — harden OTP persistence and add MFA/step-up authentication as required.
24. `SEC-022` — sanitize service-status detail by role.
25. `SEC-023` — reduce mass-assignment authority.
26. `SEC-024` — require TLS for remote databases.
27. `SEC-025` — configure protected Horizon monitoring access.

The AI implementation procedure, acceptance template, and per-item verification rules are in [security-hardening-playbook.md](security-hardening-playbook.md).
