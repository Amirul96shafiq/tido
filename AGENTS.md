# tido — Codex Project Instructions

These repository instructions govern **Codex only**. Cursor and Antigravity keep their own operating rules. Do not edit `.cursor/**` or `.agents/AGENTS.md` to propagate Codex workflow changes unless the user explicitly requests cross-agent work. Project skills under `.agents/skills/` remain available when Codex surfaces them, but the other agents' consolidated instruction files are not Codex policy.

## Mandatory Codex workflow

Resolve the workflow mode before acting. An explicit user mode wins; otherwise infer the least-mutating mode that satisfies the request:

- **Ask** — answer, explain, review, or report. Read-only inspection is allowed; do not edit files, run tests/builds/services, create branches, or perform external writes.
- **Plan** — investigate, ask focused questions, and create or update one task plan under `.codex/plans/` from `.codex/PLAN_TEMPLATE.md`. Selecting Plan explicitly authorizes this ignored task-local document. Do not change application, test, configuration, or shared documentation files; do not execute the plan.
- **Agent** — inspect, state a concise implementation and verification plan, then work immediately. Ask only when a missing decision materially changes scope, behavior, risk, or authority.
- **Debug** — reproduce and isolate the failure, add minimal temporary diagnostics when evidence is insufficient, then pause with exact user test steps when user interaction is required. Analyze returned evidence before proposing or applying the permanent fix. A Debug request alone does not authorize a permanent fix.

The selected mode applies to the current request and its follow-ups until the user switches modes or uses an unmistakable action instruction such as “implement,” “proceed,” or “fix it.” Never silently promote Ask, Plan, or Debug into Agent. “Debug and fix” authorizes the permanent fix after the fault is evidenced, but still pause when the user must perform a reproduction step.

Before any repository mutation, test/build/service execution, or Git write, read **[`.codex/CODEX_WORKFLOW.md`](.codex/CODEX_WORKFLOW.md)** completely. Select checks from **[`.codex/VERIFICATION.md`](.codex/VERIFICATION.md)**. In Plan mode, follow **[`.codex/PLAN_TEMPLATE.md`](.codex/PLAN_TEMPLATE.md)**.

## Non-negotiable gates

- Inspect the active branch, worktree, existing user changes, relevant implementation, sibling conventions, and tests before editing.
- Do not develop on `main`. In Agent mode, or before Debug-mode instrumentation, Codex may create or switch to an appropriately named `feature/*` or `fix/*` branch after confirming that doing so preserves user work. Updating `main` from the network still requires an explicit decision when it can change local state.
- If the current dirty branch belongs to an unrelated concern, stop and ask for branch/worktree direction. Do not carry dirty changes into a new concern or move them without permission.
- Read [docs/agent-onboarding.md](docs/agent-onboarding.md) and only the task-relevant architecture, domain, UI, or operations documents before implementation.
- Use Laravel Boost through `.codex/config.toml` for version-specific documentation, schema inspection, read-only database queries, URLs, and browser logs when its tools are available. If unavailable, report the limitation and use official documentation or installed source as the fallback.
- Activate every relevant Codex-surfaced skill. Architecture, security, receipt pipeline, Filament, Tailwind, Pest, and Laravel conventions are additive gates when their trigger applies.
- Treat “every change must be programmatically tested” as applying to executable behavior. Documentation- and instruction-only work uses the documentation checks in `.codex/VERIFICATION.md`; do not invent synthetic application tests for prose changes.
- An ingestion or asynchronous integration is not verified at upload, webhook acceptance, file storage, or job dispatch. When live verification is in scope and locally available, follow the queue to completion and inspect the persisted invoice status and populated data.
- Never run broad process termination such as `taskkill /F /IM php.exe`. Identify workspace-owned processes first and stop only exact targets; ask when ownership is uncertain.
- A Debug reproduction pause is an interim handoff, not completion. Temporary diagnostics may remain only when they are explicitly listed with their locations, privacy review, reproduction steps, and removal plan.
- Never commit, amend, push, force-push, create or merge a PR, deploy, or mutate production without explicit user approval for that action.
- For completed Agent work and terminal Debug work, finish by reviewing tracked and intended untracked changes plus status, then report files changed, exact checks and results, skipped checks with reasons, remaining risks, and any approval-dependent next action. Ask, Plan, and interim Debug handoffs follow their mode-specific endings.

## Project sources of truth

- Product and directory map: **[docs/agent-onboarding.md](docs/agent-onboarding.md)**
- Architecture gate: **[docs/system-architecture.md](docs/system-architecture.md)** — surface contradictions before proceeding
- Git workflow: **[docs/git-workflow.md](docs/git-workflow.md)** — short-lived `feature/*` / `fix/*` branches into `main`
- Documentation index: **[docs/README.md](docs/README.md)**
- Dashboard modules: **[docs/dashboard-views.md](docs/dashboard-views.md)** — Finances shipped; Training / Health / Task planned
- Product name: **tido** only; expense tags are **Label** / **Labels** (`Label`, never Category)

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest` for a path registered with `Vite::asset()` (panel scripts in `AdminPanelProvider`), run `npm run build` so `public/build/manifest.json` includes the new entry. `npm run dev` alone does not write those production-manifest entries; see **[docs/vite-assets.md](docs/vite-assets.md)**.
- For missing UI after CSS/`@vite` changes while the Vite server is not running, ask the user to run `npm run build`, `npm run dev`, or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
