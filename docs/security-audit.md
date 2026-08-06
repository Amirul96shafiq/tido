# Security audit and hardening register

This document is the source of truth for the tido security findings identified during the source-level pre-public audit on **6 August 2026**. It is written for humans and AI agents that will address the findings one at a time.

The register records evidence, impact, the required end state, and the verification boundary. It does not mean that a finding is fixed. Every row remains open until the implementation, focused verification, and any required deployment check are complete.

## How to use this register

- Select exactly one `SEC-*` item for a change unless one item is an unavoidable prerequisite of the selected item.
- Re-read the current source at the linked location before editing; line numbers are audit-time pointers and may move.
- Preserve the single-tenant household model and the existing `BackupService`, `HouseholdAccess`, `InvoicePolicy`, queue, and local Evolution/Ollama architecture.
- Update the selected row only after the implementation and its verification are complete.
- Do not mark a row `Verified` based only on a unit test when the control also depends on production environment, reverse-proxy, storage, firewall, or service configuration.
- Never place real secrets, restore tokens, session identifiers, raw receipt content, full webhook payloads, or unredacted upstream responses in this document, tests, or logs.

The implementation procedure is in [security-hardening-playbook.md](security-hardening-playbook.md). Repository workflow, branch, approval, and verification rules remain authoritative in [AGENTS.md](../AGENTS.md), [.codex/CODEX_WORKFLOW.md](../.codex/CODEX_WORKFLOW.md), and [.codex/VERIFICATION.md](../.codex/VERIFICATION.md).

## Audit scope and threat model

tido is a single-tenant Laravel 12 / Filament v5 household application. The Primary user has full panel access. Login-enabled Family Members have limited Finances access and may mutate only invoices attributed to their own `family_member_id`. This is not a multi-tenant or Spatie Permission design; authorization must continue to use `HouseholdAccess` and `InvoicePolicy`.

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
| **SEC-001** | Critical if deployed unchanged | Implemented | Evolution API and webhook authentication | [config/services.php](../config/services.php):62 and [.env.example](../.env.example):99 contain a known placeholder credential. The setup and curl examples in [evolution-local-windows.md](evolution-local-windows.md):135 also repeat it. [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):25 accepts the same configured value for inbound requests, while the same value is used for outbound Evolution API calls. A caller with the unchanged key can forge webhook data and potentially administer an exposed Evolution API. | Remove the fallback. Require a long random deployment secret, reject known placeholders, rotate any exposed key, separate inbound webhook verification from outbound Evolution authentication, and accept only the intended authorization header format. Update the local setup examples to use placeholders rather than a reusable literal. |
| **SEC-002** | High | Implemented | PHP dependencies | At audit time, [composer.lock](../composer.lock):1905 locked `guzzlehttp/guzzle` at `7.15.1`. The 6 August 2026 Composer audit reported high `CVE-2026-69246` and medium `CVE-2026-69245`, both fixed by Guzzle `7.15.2` or later. The [high advisory](https://github.com/advisories/GHSA-v5mv-p594-2x33) and [medium advisory](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r) describe the affected behavior. `npm audit --omit=dev` reported zero vulnerabilities. | Upgrade to at least `7.15.2`, regenerate the lock file with approval, inspect the dependency diff, rerun Composer audit, and record the result. |
| **SEC-003** | High | Open | Guest restore upload | [GuestRestoreBackupController.php](../app/Http/Controllers/GuestRestoreBackupController.php):48 builds the temporary ZIP path from `getClientOriginalName()` and uses the client name again in `move()`. Client filenames are untrusted path material and can contain traversal, reserved Windows names, or overwrite-oriented values. | Store every guest upload under a server-generated fixed filename inside a fresh directory. Validate the resolved path stays inside that directory and do not use the original name for filesystem operations. |
| **SEC-004** | High | Open | ZIP database extraction | [BackupService.php](../app/Services/BackupService.php):526 selects any archive entry ending in `.sql` or `.sqlite` and passes the unvalidated name to `extractTo()`. Unexpected directory components can turn an archive entry into a path traversal or arbitrary-file extraction risk. | Permit only exact expected database entry names, reject all directory components and alternate separators, avoid broad extraction, and verify the resolved extracted path before importing. |
| **SEC-005** | High | Open | ZIP resource limits | [BackupService.php](../app/Services/BackupService.php):420 reads and writes every application-file entry. The compressed upload limit does not establish an uncompressed total limit, per-entry limit, entry-count limit, or compression-ratio limit. A crafted ZIP can exhaust memory, disk, CPU, or request/worker time. | Inspect the central directory before extraction. Enforce entry count, total uncompressed bytes, per-file bytes, compression ratio, allowed prefixes/extensions, and a bounded restore duration before database or file writes. |
| **SEC-006** | High | Open | Restore integrity and one-time use | [GuestRestoreBackupController.php](../app/Http/Controllers/GuestRestoreBackupController.php):36 finds a catalog backup by token, but the uploaded archive is not visibly bound to that catalog row by a signature, manifest, or embedded-token comparison. The token is consumed after restoration, so concurrent submissions may race. A leaked token can authorize an attacker-created database/archive. | Sign or MAC the backup manifest, verify the submitted token and archive identity before any write, use an atomic claim/lock for one-time restore, and provide rollback or staging for failed imports. |
| **SEC-007** | High | Open | Backup confidentiality | [BackupService.php](../app/Services/BackupService.php):300 creates a native SQLite ZIP containing the database and plaintext `RESTORE_TOKEN.txt`, then embeds application files. [config/backup.php](../config/backup.php):29 includes `base_path()` for the Spatie source and [config/backup.php](../config/backup.php):191 makes archive encryption optional. This can place database contents, source, `.env`, credentials, and recovery material in a locally stored archive. | Make encryption mandatory for every backup path, fail closed when the archive key is absent, remove plaintext tokens from archives, narrow source include/exclude rules, exclude `.env` and debug artifacts explicitly, use encrypted off-host retention, rotate keys, and audit backup download/restore events. |
| **SEC-008** | Medium | Open | Restore-token lookup | [BackupService.php](../app/Services/BackupService.php):178 loads every catalog row with a token hash and performs a bcrypt check until one matches. The public restore endpoint has only the visible `5,1` throttle. A growing catalog or distributed requests can turn token lookup into CPU/DB denial of service. | Use a public token identifier with a keyed lookup plus a separately verified secret, add global and per-IP limits, bound catalog scan work, and monitor failed restore attempts without logging the token. |
| **SEC-009** | High | Open | WhatsApp webhook trust boundary | [routes/api.php](../routes/api.php) exposes the webhook without an explicit endpoint throttle. [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):32 accepts the full body, does not enforce a strict DTO/schema or body/text/message-ID limits, and trusts caller-supplied sender fields after the shared secret. A valid key can flood queues, spoof an allowlisted JID, create financial records, and replay messages. | Add provider signature verification or a private network/IP boundary, strict payload validation, size limits, sender/message-ID validation, per-IP/secret/sender throttles, replay protection, and message-ID idempotency before queue dispatch. |
| **SEC-010** | Medium | Open | Webhook credential transport | [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):25 accepts `?token=` as an alternative to the `Authorization` header. Query-string credentials can enter access logs, referrers, browser history, screenshots, and monitoring systems. | Reject query-string authentication. Accept only a header or signed request body and rotate the credential if query authentication was ever reachable. |
| **SEC-011** | Medium | Open | Webhook availability | [WhatsAppWebhookController.php](../app/Http/Controllers/Api/WhatsAppWebhookController.php):152 sends some WhatsApp replies synchronously before acknowledging the webhook. Slow upstream calls can hold web workers and amplify authenticated webhook traffic into availability pressure. | Queue outbound replies, return a bounded acknowledgement immediately, add queue concurrency/rate limits, and use explicit connect/total timeouts with bounded retries. |
| **SEC-012** | High if copied to production | Needs deployment verification | Environment and session baseline | [.env.example](../.env.example):4 uses `APP_DEBUG=true`; [.env.example](../.env.example):36 disables session encryption; [config/session.php](../config/session.php):172 leaves the secure-cookie flag to an unset environment value. The sample also documents a one-week session lifetime. The live environment was not inspected. | Require `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, intentional HttpOnly/SameSite settings, a reviewed session lifetime, and a deliberate session-encryption choice in deployment validation. |
| **SEC-013** | Medium | Open | Public changelog disclosure | [routes/web.php](../routes/web.php):21 exposes full commit hashes, author names/emails, commit descriptions, tags, avatar data, and raw exception messages through an anonymous JSON endpoint. | Decide whether the endpoint is public. If it remains public, return curated release metadata only, remove author emails and raw descriptions, bound pagination, and return generic error identifiers rather than exception text. |
| **SEC-014** | Medium | Open | Debug/agent instrumentation | [routes/web.php](../routes/web.php):24 writes `debug-8f1b08.log` into the project root on every changelog request, including raw exception text. The ignored file currently exists locally. It creates privacy, disk-growth, and deployment-artifact risk. | Remove the instrumentation before release, use redacted structured logging with rotation when diagnostics are genuinely required, and verify no debug artifacts are present in release output. |
| **SEC-015** | Medium | Open | Outbound TLS and author privacy | [ChangelogHelper.php](../app/Helpers/ChangelogHelper.php):131 calls `Http::withoutVerifying()` for GitHub avatar lookup and sends author email data to a third party. TLS integrity is disabled and lookup failures log email addresses. | Restore certificate verification, avoid email-based third-party lookups, prefer local or privacy-preserving avatars, and redact identifiers in logs. |
| **SEC-016** | Medium | Open | WhatsApp media resource handling | [ProcessWhatsAppMediaJob.php](../app/Jobs/ProcessWhatsAppMediaJob.php):375 receives JSON/base64 media and decodes it before the later binary-size check. [WhatsAppNotificationService.php](../app/Services/WhatsAppNotificationService.php):45 logs upstream response bodies on failure. A compromised upstream or valid webhook abuse path can create memory pressure or PII/log exposure. | Enforce connect/total timeouts and response limits before decoding, stream where practical, truncate/redact response logs, and bound worker concurrency and retry volume. |
| **SEC-017** | Medium | Open | Manual invoice integrity and input limits | [ManualWhatsAppInvoiceParser.php](../app/Support/ManualWhatsAppInvoiceParser.php):16 accepts negative quantities and line totals. [ProcessManualWhatsAppInvoiceJob.php](../app/Jobs/ProcessManualWhatsAppInvoiceJob.php):56 has no evident strict limits for message length, line count, item descriptions, merchant names, or aggregate value. | Reject negative values unless credit-note behavior is explicitly designed, enforce numeric ranges and message/item limits, validate again inside the job, and use a transaction for invoice plus item creation. |
| **SEC-018** | Medium | Open | Ollama prompt, output, and log handling | [OllamaService.php](../app/Services/OllamaService.php):53 logs raw upstream bodies and [OllamaService.php](../app/Services/OllamaService.php):109 logs raw AI text when JSON decoding fails. Manual WhatsApp text and receipt data are user-controlled AI input. Raw AI data can leak receipt PII, create log growth, and bypass business assumptions without strict output limits. | Bound prompt/input sizes, enforce a typed output schema and numeric/string limits, keep the Ollama host deployment-controlled, redact/truncate raw failures, and do not expose `raw_ai_response` outside authenticated authorized views. |
| **SEC-019** | Medium | Open | OTP enumeration and throttling | [Login.php](../app/Filament/Pages/Auth/Login.php):509 returns an error for unknown phone numbers while known numbers enter the OTP flow. The existing test title says “without revealing details” but does not assert uniform output. Component throttling and per-user OTP limits do not visibly prove IP-plus-phone-plus-global controls. | Return uniform user-facing responses and timing, add limits keyed by IP and normalized phone plus a global abuse control, and test the effective limit across fresh sessions and distributed attempts. |
| **SEC-020** | Medium | Needs runtime verification | Password-reset enumeration | [RequestPasswordReset.php](../app/Filament/Pages/Auth/RequestPasswordReset.php):44 uses different success and failure notification paths. The inherited Filament copy was not runtime-verified in the source-only audit, so the disclosure risk is likely but not confirmed. | Make the user-facing response identical for existing, non-existing, and non-panel accounts. Keep delivery detail in protected logs and add a test for identical output. |
| **SEC-021** | Medium | Needs deployment verification | HTTPS, headers, host, and proxy boundary | No application-level configuration was found for HTTPS enforcement, HSTS, CSP, frame protection, Referrer-Policy, Permissions-Policy, trusted proxies, or trusted hosts. The edge server may provide some of these, so source absence is not proof of deployed absence. | Configure and verify HTTPS redirects, HSTS, content/type and frame protections, a Filament-compatible CSP, Referrer-Policy, Permissions-Policy, trusted proxies, and a host allowlist at the correct application/edge layer. |
| **SEC-022** | Low | Open | Service-status information disclosure | [ServiceStatusPage.php](../app/Filament/Pages/ServiceStatusPage.php):78 allows authenticated Family Members to view service reports. [OllamaProbe.php](../app/Services/Health/Probes/OllamaProbe.php):43 and the queue probes can expose model names, drivers, failed-job counts, or raw exception detail. | Show sanitized health status to Family Members and detailed diagnostics only to Primary/admin users. Never display raw exception messages, credentials, or connection strings. |
| **SEC-023** | Low | Open | Mass assignment defense in depth | [User.php](../app/Models/User.php):26 includes authority fields such as `household_role` and `family_member_id` in `$fillable`. [Invoice.php](../app/Models/Invoice.php):23 and [Backup.php](../app/Models/Backup.php):23 include internal ownership, status, path, and restore fields. No direct public exploit was confirmed, but future request-to-model use could become privilege or data-integrity bypass. | Remove privileged/internal fields from broad fillable lists. Use dedicated DTOs/services, policies, and trusted `forceFill()` boundaries, with tampering tests for ownership, role, source, status, and restore fields. |
| **SEC-024** | Low | Needs deployment verification | Remote database transport | [config/database.php](../config/database.php):99 defaults PostgreSQL to `sslmode=prefer`. The current local project uses SQLite, but a remote PostgreSQL deployment could silently fall back to an unencrypted connection. | Require certificate-verified TLS for remote PostgreSQL and explicit TLS settings for MySQL when used. |
| **SEC-025** | Low / operational | Needs deployment verification | Horizon access and monitoring | [HorizonServiceProvider.php](../app/Providers/HorizonServiceProvider.php):31 has an empty `viewHorizon` allowlist. This denies access rather than exposing Horizon, but it weakens incident response and queue-abuse investigation. | Configure an explicit production allowlist or role-based gate, keep strong authentication, and verify the dashboard is accessible only to intended operators. |
| **SEC-026** | Info / enhancement | Open | Session persistence and MFA | [Login.php](../app/Filament/Pages/Auth/Login.php):684 defaults OTP `remember` to true when the field is absent. Password login has no clearly enforced second factor for Primary users. | Default OTP persistence to false, make it explicit, add recent-authentication requirements for destructive/security-sensitive actions, and introduce MFA/recovery codes for Primary accounts if the threat model requires it. |
| **SEC-027** | Medium | Open | Queue idempotency and external-call retries | Media and manual WhatsApp jobs have retry/backoff behavior but are not visibly unique jobs. Duplicate deliveries can repeat downloads, parsing, notifications, or financial work before database deduplication. External HTTP clients also need consistent connect/total timeouts and bounded retry policy. | Make processing idempotent at the job boundary using upstream message IDs, add unique jobs or locks where appropriate, enforce queue rate limits, and test duplicate delivery and retry exhaustion. |

## Controls already present

These controls were observed and should be preserved while addressing the open items:

- [InvoicePolicy.php](../app/Policies/InvoicePolicy.php) and `HouseholdAccess` provide household-aware mutation authorization; [InvoiceFamilyMemberOwnershipTest.php](../tests/Feature/InvoiceFamilyMemberOwnershipTest.php) covers important Family Member boundaries.
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
- Focused verification passed: 79 targeted tests with 473 assertions, Pint, and `git diff --check`.
- The latest full-suite run reported 899 passing tests and six failures in notification CSS, changelog/database notification slide-overs, dashboard section navigation, label form copy, and service-health aggregation; none exercise the SEC-001 credential boundary.
- Focused Larastan still reports five warnings at unchanged locations in `EvolutionInstanceService` and `WhatsAppNotificationService`; the new credential class and webhook controller pass focused analysis.
- Deployment verification remains required: rotate or revoke any previously exposed/shared credential, create two distinct live secrets, configure them in the live environments, re-register the Evolution webhook, and verify the production Evolution and reverse-proxy boundary. The finding is **Implemented**, not **Verified**, until those operational checks are complete.

## Suggested implementation order

The order reduces the chance of implementing a later control on top of an unsafe boundary. Each item is still a separate change unless an unavoidable prerequisite is explicitly recorded:

1. `SEC-002` — patch the affected runtime dependency.
2. `SEC-001` — remove and rotate the shared/default Evolution credential.
3. `SEC-003` — remove client filenames from guest restore storage.
4. `SEC-004` — constrain database archive paths.
5. `SEC-005` — add ZIP resource limits.
6. `SEC-006` — bind archives to tokens and make restore consumption atomic.
7. `SEC-007` — enforce encrypted, scoped backups.
8. `SEC-009` — harden webhook schema, sender trust, rate limits, replay, and idempotency.
9. `SEC-010` — remove query-string authentication.
10. `SEC-011` — make webhook replies asynchronous and bounded.
11. `SEC-027` — make processing idempotent and external retries bounded.
12. `SEC-012` — enforce the production session/debug baseline.
13. `SEC-021` — enforce the production transport, header, host, and proxy baseline.
14. `SEC-013` — reduce public changelog disclosure.
15. `SEC-014` — remove debug/agent instrumentation.
16. `SEC-015` — restore outbound TLS verification and author privacy.
17. `SEC-016` — bound media retrieval and response logging.
18. `SEC-017` — enforce manual invoice integrity and input limits.
19. `SEC-018` — bound Ollama input/output and redact AI logs.
20. `SEC-019` — harden OTP enumeration and throttling.
21. `SEC-020` — make password-reset responses enumeration-resistant.
22. `SEC-026` — harden OTP persistence and add MFA/step-up authentication as required.
23. `SEC-022` — sanitize service-status detail by role.
24. `SEC-023` — reduce mass-assignment authority.
25. `SEC-024` — require TLS for remote databases.
26. `SEC-025` — configure protected Horizon monitoring access.

The AI implementation procedure, acceptance template, and per-item verification rules are in [security-hardening-playbook.md](security-hardening-playbook.md).
