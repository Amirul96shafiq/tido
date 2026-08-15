# tido — Antigravity IDE Agent Rules

> Workspace-scoped rules for Antigravity IDE. Consolidates project conventions that Cursor loads from `.cursor/rules/*.mdc`. Shared docs in `docs/` are IDE-agnostic.

---

## Project Overview

Single-tenant personal hub. **Finances** is shipped (MYR expense & receipt tracking). **Training**, **Health**, and **Task** are planned dashboard modules (coming-soon placeholders). See `docs/dashboard-views.md`.

Finances today: ingest receipts (WhatsApp image or text manual expense, Filament upload), parse with local Ollama, categorize line items, detect duplicates, show budgets/analytics.

### Naming

- Product / repo / runtime: **tido** only (`admin@tido.local`, WhatsApp "tido Bot")
- Expense categories in code/DB: **Label** (not "Category"); UI copy: **Label** / **Labels**

### Stack (authoritative)

Laravel 12 · Filament v5 · Livewire 4 · Tailwind v4 · SQLite (local) / PostgreSQL 17 (prod) · Ollama · Evolution API · Pest v3 · Windows host dev

Prefer this over older version numbers in `docs/system-architecture.md`.

### Single-tenant household

No multi-tenancy package. One Filament panel with **household roles** (`primary` vs `family_member`): Primary owns settings; login-enabled Family Members get limited Finances access. Receipts are attributed via `expenses.family_member_id` (**Uploaded By**). See `docs/household-access.md`.

### Agent entry points

1. Read `docs/agent-onboarding.md` for the full map
2. Activate skills under `.agents/skills/` when in that domain
3. Use Laravel Boost MCP (`search-docs`, `database-schema`, `database-query`) before inventing APIs — if MCP is configured
4. If a request contradicts `docs/system-architecture.md`, halt and warn the user
5. Use feature/fix branches per `docs/git-workflow.md` — do not develop features on `main`
6. Dashboard modules (Finances / Training / Health / Task): `docs/dashboard-views.md`
7. UI icon CTAs: Filament Tippy tooltips — `docs/ui-tooltips.md`
8. Sticky top/bottom bars + blur veil — `docs/ui-sticky-blur.md`
9. Vite panel assets (`Vite::asset` / when to `npm run build`) — `docs/vite-assets.md`
10. Sticky section tabs + smooth scroll — `docs/ui-section-nav.md`
11. Single-line text marquee (reusable) — `docs/ui-text-marquee.md`
12. Notes rich editor (`notes` fields) — `docs/ui-notes-rich-editor.md`
13. Field character limits (`current/max` counters) — `docs/ui-field-character-limits.md`
14. Resource form empty placeholders / defaults — `docs/ui-form-empty-defaults.md`
15. Custom Blade toggles (Filament color classes + Profile inlineLabel) — `docs/ui-custom-toggles.md`
16. Backups / Danger Zone / guest restore — `docs/backups-and-danger-zone.md`
17. Service Status / health probes — `docs/service-status.md`
18. Profile Active Sessions — `docs/active-sessions.md`
19. Household access / family login / attribution — `docs/household-access.md`
20. WhatsApp text manual expenses — `docs/whatsapp-manual-expense.md`
21. WhatsApp bot commands / keywords — `docs/whatsapp-bot-commands.md`

### Do not

- Hit real Ollama/Evolution in tests — use `Http::fake()` / `Queue::fake()`
- Add new top-level `app/` folders without approval
- Change dependencies without approval
- Treat stock `README.md` as product docs
- Call the product anything other than **tido**
- Develop features directly on `main` (use `feature/*` or `fix/*` → PR → `main`)

### Windows Composer File Locks
- **CRITICAL**: On Windows, when `npm run dev:all` is stopped, orphaned `php.exe` processes (like `artisan serve` or `queue:listen`) may stay alive in the background and hold file locks on `vendor/` and `bootstrap/cache/`. This will cause `composer` commands (like `composer update` or `dump-autoload`) to hang indefinitely.
- **Always** run `taskkill /F /IM php.exe` to terminate orphaned processes before running Composer commands if dev servers were recently running or interrupted.

---

## PHP & Laravel Conventions

### Required on every PHP file

```php
<?php

declare(strict_types=1);
```

Typed params/returns, constructor property promotion, enums + `match` where appropriate. PSR-12. Run `vendor/bin/pint --dirty --format agent` after edits.

### Models

- Match sibling models: domain models use `protected $casts = [...]`; `User` uses `casts()` method
- Money: `decimal(12,2)` columns, cast `'decimal:2'`, currency default `MYR`, UI as `RM {amount}`
- SoftDeletes + Spatie `LogsActivity` on Expense, ExpenseItem, Label, Budget
- Prefer Eloquent/Query Builder — never raw user input in SQL

### Enums

Backed string enums with `label()` and `options()` helpers (`app/Enums/`).

### Jobs

`ShouldQueue`, promoted constructor props, inject services in `handle()`, implement `failed()` when status must update. Retries/backoff like `ExtractReceiptDataJob` (`$tries = 3`, backoff `[30, 60, 120]`).

### Side effects

Put hash generation, job dispatch, and budget alerts in observers/services — not Filament pages or controllers when possible.

### Validation

Prefer Form Requests for HTTP controllers. Filament forms validate via component rules. Webhooks: auth + payload validation first, then queue work.

### Architecture gate

Do not invent schema/workflows that contradict `docs/system-architecture.md` without warning the user.

---

## Filament v5 Conventions

Panel: `admin` at `/admin` — configured only in `AdminPanelProvider` (no `config/filament.php`).

### Resource layout (required)

```
app/Filament/Resources/{Plural}/
  {Singular}Resource.php
  Schemas/{Singular}Form.php      # Form::configure(Schema $schema) — also used for View
  Tables/{Plural}Table.php        # Table::configure(Table $table)
  Pages/List|Create|Edit{...}.php
```

Use Filament `Schema` for forms (v5), not legacy Form API. Prefer native Form/Table builders over custom Blade. Do **not** add `Schemas/{Singular}Infolist.php` or override `Resource::infolist()` for table View slide-overs.

### Navigation

- Groups: **Finances** (Upload Receipts, Expenses, Budgets), **Settings** (Labels, Payment Methods, Family Members), **Integrations** (Evolution API), **Tools** (Backups, Service Status) — Tools is last
- Home dashboard modules (Finances / Training / Health / Task icon tabs): see `docs/dashboard-views.md` — not sidebar nav groups
- **Primary-only** Settings / Tools / Integrations: use `RequiresPrimaryHouseholdAccess` (or `HouseholdAccess::isPrimary()`). Family members get Finances (Upload, Expenses, Budgets, Recurrings) + Profile; Budgets/Recurrings create stays primary-only — see `docs/household-access.md`
- Theme: amber/zinc accents + Slate dark surfaces (see `docs/ui-dark-theme.md`), Outfit font, SPA mode, collapsible sidebar, database notifications
- Breadcrumbs: Filament native panel breadcrumbs with `PrependsHomeBreadcrumb`. Kept visible on small screens via `.fi-header .fi-breadcrumbs` override in `app.css`. Do not disable panel-wide or replace with a custom back link. When adding a new Create/Edit/List/custom page, use `PrependsHomeBreadcrumb` and register Create/Edit pages in the `PAGE_END` draft-poller scopes.
- Custom pages: `Dashboard`, `ReceiptUploadPage`, `Auth/EditProfile`, `EvolutionApiPage`, `ServiceStatusPage`

### Widgets

Dashboard **Finances** widgets live in `app/Filament/Widgets/`. Month filtering uses `InteractsWithDashboardMonth` + `DashboardMonthAnalytics` / `DashboardMonthPeriod`. Spender filter uses `DashboardSpenderScope` (`all` / `primary` / `family:{id}`) — `docs/household-access.md`. Training / Health / Task views are coming-soon shells until their modules ship — `docs/dashboard-views.md`.

### Content draft recovery

Create/Edit resource pages use `App\Filament\Concerns\RecoversContentDraft` (Expenses, Labels, Budgets). See `docs/content-draft-recovery.md` when adding a new Create/Edit page: trait + `contentDraftKey()` + register the page in `AdminPanelProvider` `PAGE_END` poller scopes.

### Sticky form actions (bottom blur)

Create/Edit resource pages and `Auth/EditProfile` must use `App\Filament\Concerns\HasStickyBlurFormActions` so Create / Save / Cancel (and other `getFormActions()` CTAs) stick to the bottom with the frosted blur veil. See `docs/ui-sticky-blur.md` — do not call Filament `stickyFormActions()`.

### Section nav (sticky tabs)

Multi-section Create/Edit resource pages and custom content pages with **2+ in-page sections** must use `HasSectionNav` (or `HasStickyBlurFormActions`, which composes it) with `sectionNavItems()`, stable `->id()` anchors on each section, page-scoped CSS, and section-nav tests. See `docs/ui-section-nav.md` — do not invent a second tab pattern.

### Notes fields (rich editor)

Any form field backed by a `notes` (or note-like HTML) column must use `App\Filament\Forms\Components\NotesRichEditor`, not `Textarea` or a one-off `RichEditor`. Shared toolbar + `.fi-notes-rich-editor` height live in that component / `app.css`. See `docs/ui-notes-rich-editor.md`.

### Field character limits

Identity and notes text fields use `TextInput::characterLimit()` / the `NotesRichEditor` default (`App\Support\FieldCharacterLimits`) with a live `{current}/{max}` counter. Do not hand-roll `maxLength()` plus a one-off hint. See `docs/ui-field-character-limits.md`.

### Form empty placeholders / defaults

When adding or extending a resource `*Form.php`, give empty Create/Edit fields a clear empty state: `placeholder()` for UI hints (not saved), `default()` for real starting values, and restore-on-blur only when an empty value breaks UX. See `docs/ui-form-empty-defaults.md`.

### Custom Blade toggles

Prefer native `Toggle::make()`. When a custom Alpine toggle is required (e.g. Filament `$store.sidebar` / localStorage), build on/off classes with `get_component_color_classes(ToggleComponent::class, 'primary'|'gray')` — never only `fi-color-primary`. On Edit Profile, mirror `fi-fo-field-has-inline-label` markup. See `docs/ui-custom-toggles.md`.

### Edit page title

Every `EditRecord` page must use `App\Filament\Concerns\AppendsResourceLabelToEditTitle` so the heading ends with the singular model label (e.g. `Edit Overall Budget · Monthly 2026 Budget`). The record-title segment is highlighted with primary color (`text-primary-600 dark:text-primary-400`). Does not apply to Create pages, List pages, or `EditProfile`.

### Global search (Global Search Modal)

Panel uses `charrafimed/global-search-modal` with `globalSearchResourceOptIn()` — resources must **explicitly** opt in on the resource class itself.

**Page & section navigation** is merged via `AdminDestinationSearch` in `AdminPanelProvider`. When adding a new resource index page, custom Filament page, or in-page section that should be searchable:

1. Register the page/section in `app/Filament/GlobalSearch/AdminDestinationSearch.php`
2. For form sections, add a stable `->id('kebab-case')` on `Section::make(...)`
3. Section result URLs use `#anchor` fragments; SPA scroll is handled by `<x-hash-scroll />`

When adding a new `*Resource.php` (record search):

1. Decide whether records should appear in global search
2. If yes: set `protected static bool $isGloballySearchable = true` on the resource class, plus `$recordTitleAttribute` and `getGloballySearchableAttributes()`
3. Add `getGlobalSearchEloquentQuery()` when searching relationship dot-notation
4. Override `getGlobalSearchResultUrl()` for index-only resources
5. Set `$globalSearchSort` for sensible result group ordering

### View records (required)

Always use slide-over view — never add a dedicated `ViewRecord` / `View{Model}` page.

- Resource tables: `ViewAction::make()->slideOver()` in `recordActions` (ungrouped, left of `RecordActionsGroup`)
- **Schema:** rely on Filament's default ViewAction behavior — the slide-over renders the resource **form** schema disabled. Do **not** define `public static function infolist(...)` on the resource and do **not** add a separate `*Infolist.php`.
- Notification / deep-link "View" CTAs: open the resource list with Filament table-action query params so the same slide-over mounts:

```php
Resource::getUrl('index', [
    'tableAction' => 'view',
    'tableActionRecord' => $record->getRouteKey(),
]);
```

### Prefer

- `relationship()` for related selects
- Notifications on user-facing actions
- Soft-delete aware queries
- Icon-only ungrouped row actions (typically View): set globally in `AppServiceProvider` via `Table::configureUsing`
- Resource table kebab: keep `ViewAction` ungrouped (left of the ⋮); wrap Edit / Delete / custom row actions in `RecordActionsGroup`
- Filter + Column Manager triggers: Tippy tooltips globally
- List-page "New …" CTAs: plus icon set globally via `CreateAction::configureUsing`
- `created_at` columns use `->since()->dateTimeTooltip()` — relative time in the cell, full date/time on hover
- Icon CTA tooltips: Filament Tippy only (`->tooltip()` / `x-tooltip`) — never browser `title=`

### Avoid

- Custom Blade unless native components cannot do the job
- Putting business logic (Ollama, alerts) inside Resource classes — call Services/Jobs
- Dedicated View pages (`ViewRecord`, `make:filament-page --type=ViewRecord`, `getPages()` `view` routes)
- Custom resource `infolist()` / `Schemas/*Infolist.php` for table View slide-overs
- Omitting View from resource table `recordActions` (except Backups)
- Flat Edit/Delete/custom row actions outside `RecordActionsGroup` (Upload Receipts / Recent Receipts excepted)
- Visible text labels on ungrouped table record actions (icons only)
- Raw `->dateTime()` alone on `created_at` resource columns — use `since()` + `dateTimeTooltip()`
- Browser-native `title=` tooltips on icon CTAs

---

## Receipt Pipeline Rules

### Flow (image)

```
Image → Expense (pending) → ExpenseObserver → ExtractReceiptDataJob
  → OllamaService + ReceiptExtractionPrompt
  → Expense (parsed) + ExpenseItems → BudgetAlertService
```

### Flow (WhatsApp manual text)

```
Text format → ProcessManualWhatsAppExpenseJob → pending Expense (no image)
  → Manual expense received ack → ParseManualWhatsAppExpenseJob
  → Ollama text labels → requires_manual_review → Manual expense parsed reply
```

Format + payment tokens: `docs/whatsapp-manual-expense.md`.

Statuses: `pending` → `parsed` → `reviewed` | `requires_manual_review` | `failed`
Sources: `manual` | `whatsapp`

### Ollama (mandatory)

- POST with `"format": "json"`
- Strip markdown fences with regex before `json_decode` (`OllamaService::cleanAndDecodeJson`)
- Vision prompt/schema: `app/Prompts/ReceiptExtractionPrompt.php`
- Manual text labels: `app/Prompts/ManualExpenseLabelPrompt.php` via `OllamaService::generateJson` (no `images`)
- Map AI `label` (legacy `suggested_category`) via `LabelMatcher` → `Label` where `type = Finance`

### Duplicate detection

`receipt_hash = sha256(invoice_number + date_time + total_amount)` on create (`ExpenseObserver`). Unique DB constraint — handle collisions gracefully.

### WhatsApp (Evolution API)

- `POST /api/webhooks/whatsapp` — `Authorization: Bearer <EVOLUTION_WEBHOOK_SECRET>` from `config('services.evolution.webhook_secret')`; outbound Evolution calls use `config('services.evolution.api_key')`
- Validate auth/payload first; heavy work via queue/jobs
- Images → store → pending Expense; text keywords (`spend`/`total`) → monthly total reply
- Text manual expense format → pending Expense (no image) → label job → `requires_manual_review`
- Attribute WhatsApp expenses with `ExpenseSenderAttribution` (`family_member_id`; null = Primary) — `docs/household-access.md`
- Optional merchant payment token: aliases from Settings → Payment Methods

### Queues

Horizon monitors Redis queues `default`, `receipts`, `whatsapp`. New long AI work should use retries + `failed()` → `requires_manual_review`.

---

## Testing Conventions

Use Pest v3. Activate `.agents/skills/pest-testing` when writing/editing tests.

### Baseline

```php
uses(RefreshDatabase::class);

$this->actingAs(User::factory()->create());
```

Create tests with `php artisan make:test --pest {Name} --no-interaction`.

### Always mock externals

```php
Http::fake([...]);      // Ollama, Evolution API
Queue::fake();           // assert ExtractReceiptDataJob pushed
Storage::fake('local');
```

Never call real Ollama/Evolution in CI/tests.

Family / household: use `FamilyMember::factory()->loginEnabled()` when testing panel login or ownership; see `docs/household-access.md`.

### Patterns in this repo

- Filament: often HTTP `get(Resource::getUrl('index'))`; Livewire for interactive pages (`Livewire::test(EditProfile::class)`)
- Analytics without jobs: `Expense::unsetEventDispatcher()` when factories would dispatch extraction
- Seed labels when testing category mapping: `LabelSeeder`
- Factories for all models; MYR amounts and precomputed `receipt_hash` on Expense

### Run

```bash
php artisan test --compact --filter=RelevantTest
```

Every behavior change needs a new or updated test that passes.

---

## Git Workflow

The user must review content before anything is published.

### Needs approval first

- `git push` / `git push -u` / force push
- `git commit` / `git commit --amend`
- `gh pr create` / `gh pr merge` / other `gh pr` write actions
- Any compound shell command that includes the above

### May run without asking

- Read-only git: `git status`, `git diff`, `git log`, `git show`, `git branch` (list only)
- Inspecting PR state: `gh pr view`, `gh pr list`, `gh pr checks` (read-only)

### Agent workflow

1. Prepare the PR locally (branch, commits already reviewed with the user if new commits are needed)
2. Show what will be pushed / what the PR will contain (summary + key files)
3. Ask for explicit approval, then run push / `gh pr create` only after yes

### Commit Message Format

When auto-generating or writing commit messages, they must follow this exact format:

```text
<type>:<title>
- <info 1>
- <info 2>
- <info 3>
```

- **Type**: `feat`, `fix`, `refactor`, or `docs`.
- **Title**: A short description of the committed changes.
- **Info**: A bulleted list of 2-5 description pointers detailing what changed in the commit.
