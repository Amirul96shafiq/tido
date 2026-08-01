# Codex verification matrix for tido

Select checks before editing and scale them to risk. Start with the narrowest test that proves the changed behavior, then widen coverage for shared or cross-layer paths. Record every command and result in the handoff.

## Baseline for every repository change

```powershell
git diff --check
git diff
git status --short --branch --untracked-files=all
git ls-files --others --exclude-standard
```

`git diff` and `git diff --check` do not inspect untracked content. Enumerate every intended untracked file, read its complete contents, and apply the same whitespace, secret, link, and correctness checks without staging it. Also re-read the request, inspect unrelated changes, and confirm no temporary debug artifacts or secrets entered the terminal diff.

## Documentation and Codex instructions

Required when only Markdown, `AGENTS.md`, `.codex/*.md`, or other prose guidance changes:

- Run `git diff --check`.
- Resolve every changed relative Markdown link from the file containing it.
- Verify every changed local anchor against the target heading or explicit ID.
- Search changed guidance for stale terminology, contradictory commands, and renamed paths.
- Compare intentional mirrors byte-for-byte when those mirrors are explicitly in scope.
- Confirm instruction precedence and make sure Codex-only changes did not alter `.cursor/**` or `.agents/AGENTS.md`.
- Validate `.codex/config.toml` separately when it changed.

Do not add a synthetic Pest test for prose-only changes.

## Laravel/PHP behavior

During implementation, run the directly affected Pest file or filter:

```powershell
php artisan test --compact tests/Feature/RelevantTest.php
php artisan test --compact --filter=RelevantTest
```

After modifying PHP:

```powershell
vendor/bin/pint --dirty --format agent
```

Run the affected tests again after Pint when formatting changed executable files. Use Larastan for shared services, models, jobs, policies, controllers, support classes, or changes whose type impact is not isolated:

```powershell
vendor/bin/phpstan analyse
```

An ordinary Agent handoff defaults to targeted affected tests plus the other surface-specific checks in this matrix. Run the full suite for broad or high-risk changes, shared cross-cutting behavior, and every PR-ready or publication handoff unless the user explicitly chooses a narrower handoff or an environmental blocker is documented:

```powershell
php artisan test --compact
```

## Pest test changes

- Activate the Pest skill before writing or editing tests.
- Use factories and existing factory states.
- Use `RefreshDatabase` where persistence isolation is required.
- Fake queues, storage, mail, HTTP, notifications, and processes at the correct boundary.
- Use `Process::preventStrayProcesses()` for external command paths.
- Never call real Ollama, Evolution, Drive, or other live services from automated tests.
- Prove the new assertion fails against the old behavior when that can be checked safely; do not weaken an existing assertion.

## Database and migrations

Before design:

- Inspect the actual schema with Laravel Boost `database-schema`.
- Read the current model casts, fillable/guarded policy, factory, seeder, relationships, indexes, and neighboring migrations.

Verify:

- Migration applies from the current supported schema.
- Affected model and feature tests pass.
- Existing column attributes are preserved when modifying a column.
- Rollback behavior and irreversible data transformations are explicitly understood.
- PostgreSQL production compatibility is considered even when tests use SQLite in memory.

Never run a destructive migration or mutate real data merely to verify a design.

## Filament, Livewire, and Blade UI

- Run the directly affected Feature, Livewire, or Filament tests.
- Test authorization for Primary and Family Member roles when visibility or mutation changes.
- Test deep links, actions, validation, empty states, breadcrumbs, tooltips, dark mode classes, and responsive behavior that the change touches.
- Use browser verification for appearance or interaction claims; HTTP assertions alone do not prove visual behavior.
- Inspect recent browser logs after exercising the flow.
- Activate `filament-reviewer` after changes under `app/Filament/` or `resources/views/filament/`.

## Tailwind, JavaScript, and Vite assets

Run:

```powershell
npm run build
```

This is mandatory after adding, renaming, or removing a `Vite::asset()` entry and for changes whose correctness depends on compiled frontend assets. Also exercise the affected UI in a browser and check console errors. Use the Tailwind skill whenever its trigger applies.

The repository currently has no canonical JavaScript unit-test, ESLint, or Prettier quality script. Do not claim those checks ran; report the gap if relevant.

## Queues and asynchronous integrations

Automated verification:

- Fake external HTTP/process/storage boundaries.
- Assert the correct job, queue, retry/backoff, failure state, idempotency key, and persisted transition.
- Cover both accepted input and rejection/failure behavior.

Authorized local end-to-end verification when the integration itself changed:

1. Confirm the required local services and exact queue worker are running.
2. Submit one controlled test input through the real entry point.
3. Observe the job leave the queue or record its failure.
4. Inspect logs without exposing secrets or personal payloads.
5. Query the resulting record through Boost's read-only database tool.
6. Confirm final status, extracted fields, relationships, totals, and expected notifications or acknowledgements.
7. Clean up only test artifacts whose deletion was explicitly authorized.

For receipt ingestion, a successful upload, stored image/PDF, webhook response, or dispatched job is insufficient. Verify the final invoice and its items.

## Configuration and local operations

- Validate syntax with the owning command or parser.
- Compare documented commands with actual `composer.json`, `package.json`, Artisan commands, and scheduler registration.
- Avoid printing `.env` contents or secrets.
- On Windows, inspect process ID, executable path, and command line before stopping a process. Never use a broad `taskkill /F /IM php.exe` cleanup.
- Distinguish local database queues from production Redis/Horizon behavior.

## Security-sensitive changes

Activate `security-reviewer` after auth, webhook, upload, backup/restore, signed download, API, Horizon, configuration, or other security-boundary changes.

Verify at minimum:

- Authentication and authorization for success and denial paths.
- Validation, MIME/type/size limits, path handling, and secret redaction.
- Replay, duplicate, idempotency, and signed-link behavior where applicable.
- Family Member versus Primary access boundaries.
- No live external call occurs in automated tests.

## Dependency changes

Dependency changes require explicit user approval before editing lockfiles or manifests. After approval, use the relevant package manager's supported validation, run affected tests, run the full suite and frontend build when applicable, inspect dependency audit output, and report lockfile impact.

## Handoff evidence

Report:

- What outcome is proven.
- Files changed.
- Exact commands/checks and pass/fail counts.
- Browser or live-flow steps and observed state when applicable.
- Checks skipped or unavailable and why.
- Pre-existing failures separated from regressions.
- Remaining risks and the next action requiring user approval.
