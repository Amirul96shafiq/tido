# Integration Pages — Agent Reference

Canonical structure and conventions for all pages under the **Integrations** sidebar nav group (`navigationGroup: 'Integrations'`). Derived from the two shipped integrations — Ollama and Evolution API — so future **full** integrations replicate the same shape, patterns, and UI voice.

## Navigation tree

Parents are URL-less sidebar items (`AdminPanelProvider` `navigationItems()`). Children use `$navigationParentItem` in the same group. Hover (desktop) / tap (viewport `< lg`) opens a flyout from the published sidebar item — children are **not** inlined under the parent.

```
Integrations
  WhatsApp
    Evolution API          live (Active pill when latest evolution health sample is operational)
    Official API           coming soon
  AI Parsing Engine
    Gemini                 coming soon
    Ollama (Local)         live (Active pill when latest ollama health sample is operational)
    OpenAI                 coming soon
```

Constants live in `App\Filament\Support\IntegrationNavigation`. Parent visibility matches `HouseholdAccess::canManageHouseholdSettings()`.

Coming-soon children are thin pages (`PrependsHomeBreadcrumb` + `RequiresPrimaryHouseholdAccess` + `RendersComingSoonIntegration`). They do **not** follow the full checklist below (no setup wizard, settings class, or ops guide) until the integration ships. Runtime parsing remains Ollama; WhatsApp transport remains Evolution API until an exclusive-switch feature exists.

Live pages:

| Page | Parent | Slug | Sort |
|------|--------|------|------|
| `EvolutionApiPage` | WhatsApp | `evolution-api` | 10 |
| `OllamaPage` | AI Parsing Engine | `ollama` | 20 |

Placeholder pages: `WhatsAppOfficialApiPage` (sort 20), `GeminiPage` (sort 10), `OpenAiPage` (sort 30).

---

## 1. Class anatomy

Every integration page:

```php
class <Service>Page extends Page implements /* HasTable if a log table is present */
{
    use HasSectionNav;
    use PrependsHomeBreadcrumb;
    use RequiresPrimaryHouseholdAccess;
    use InteractsWithTable; // only when the page includes a history/log table
}
```

Navigation constants (set as class-level attributes or protected statics):

```php
protected static ?string $navigationGroup = IntegrationNavigation::GROUP;
protected static ?string $navigationParentItem = IntegrationNavigation::AI_PARSING_ENGINE; // or WHATSAPP
protected static ?int $navigationSort = 30; // next multiple of 10 within the parent
protected static ?string $slug = 'my-service';
protected static ?string $navigationIcon = 'icon-my-service';
protected static ?string $navigationLabel = 'My Service';
```

The custom SVG icon must live in `resources/svg/` and be registered in `AppServiceProvider` (follow the `icon-ollama` / `icon-evolution-api` pattern).

---

## 2. Required public properties

The Blade content layer reads these reactive Livewire properties directly. Every integration page **must** expose all of them.

| Property | Type | Values / purpose |
|---|---|---|
| `$connectionStatus` | `string` | `'unknown'` · `'operational'` / `'open'` · `'degraded'` · `'down'` / `'close'` |
| `$statusMessage` | `string` | Human-readable one-liner matching the status |
| `$latencyMs` | `int` | Last measured round-trip latency (ms); `0` when unknown |

Add service-specific config properties (host/URL, API key, timeouts, binary paths, etc.) as plain public properties. Their initial values come from the settings class or `.env` via `mount()`.

Also include at least one "settings source" flag:

```php
public bool $usingSavedSettings = false;
public bool $setupComplete = false;
```

These drive `settingsSourceLabel()` (see §10).

---

## 3. Required methods

| Method | Contract |
|---|---|
| `mount()` | Inject settings/detector services via constructor or method injection; call the load/detect/refresh methods; do not trigger side effects (notifications, mutations) during mount |
| `content(Schema $schema)` | Return the Blade partial wrapped in section nav scope — see §4 |
| `sectionNavItems(): array` | Return 3–4 `SectionNavItem` objects matching the `#id` anchors in the content partial |
| `sectionNavAriaLabel(): string` | Return `'<Service> sections'` |
| `getHeaderActions(): array` | Return Refresh + primary CTA + overflow `ActionGroup` — see §6 |
| `refreshStatus(bool $allowSideEffects = false)` | Idempotent poller; re-probe the service; only trigger notifications/mutations when `$allowSideEffects` is `true` |
| `testConnection(?string $host = null)` | Probe once; emit a `success` or `danger` notification with the result |
| `settingsSourceLabel(): string` | Return one of `'Setup complete'` / `'Using saved settings'` / `'Using environment defaults'` |

---

## 4. Blade content file

**Path:** `resources/views/filament/pages/partials/<slug>-content.blade.php`

The `content()` method renders it:

```php
public function content(Schema $schema): Schema
{
    return $schema->components([
        $this->buildSectionNav([
            View::make('filament.pages.partials.<slug>-content'),
        ]),
    ]);
}
```

### Section layout

Top two sections sit in a two-column grid on `xl` screens; bottom sections span full width:

```html
<div class="flex flex-col gap-6">

    {{-- Top row: 2-col on xl --}}
    <div class="grid gap-6 xl:grid-cols-2">
        <div id="<slug>-status" ...>   <!-- Status card -->
        <div id="<slug>-config" ...>   <!-- Config / connection card -->
    </div>

    {{-- Full-width sections --}}
    <div id="<slug>-pipeline" ...>   <!-- Readiness / details -->
    <div id="<slug>-activity" ...>   <!-- Activity / history log -->

</div>
```

Some integrations swap the column order (Evolution API puts the interactive "Link device" panel on the right and the read-only connection info on the left). Either ordering is acceptable; keep the section IDs consistent with `sectionNavItems()`.

### Mandatory section IDs and their contents

| # | `id` | Width | Content |
|---|---|---|---|
| 1 | `<slug>-status` | Half (top-left or top-right) | Animated status icon, status label + `$statusMessage`, 3 `<x-tido.detail-row>` summary rows, "View details" slide-over button |
| 2 | `<slug>-config` | Half (complementary to status) | Connection config (host, instance, API URL), current state badge, step-by-step usage instructions, QR / pairing UI when applicable |
| 3 | `<slug>-pipeline` | Full width | Readiness checks grid (4 cards with `Ready` / `Needs attention` badges), or a linked-entity list with inline actions |
| 4 | `<slug>-activity` | Full width | Stat cards with sparkline data **or** a Filament `InteractsWithTable` history/log table |

Always include a `wire:poll.5s.keep-alive="refreshStatus"` attribute on the outermost element during transient states (QR on-screen, connecting); remove it (return `null` from `getPollingInterval()`) when the service is stable. See §8.

---

## 5. Setup wizard — configure modal

Triggered by the primary header action ("Start Configure" / "Edit…"):

- Modal size: `ExtraLarge` (`3xl`)
- Implemented as a static Schema class:
  `app/Filament/Pages/Schemas/<Service>SetupForm.php`
- Entry point: `public static function components(): array`
- The `configureSetupAction()` method on the page injects these components into an `Action` modal

### Fieldset step order

Number fieldset labels as `01 – Step name`, `02 – …`, etc. Gate each step with `->visible(fn () => ...)` based on live reactive state so users only see steps relevant to the current setup progress.

| Step | Fieldset label | Always shown | Content |
|---|---|---|---|
| 01 | Detect `<Service>` | Yes | Detection status partial; Download / Start / Recheck actions |
| 02 | `<Service>` connection | Yes | `TextInput host` (or API URL + key); "Test connection" inline action |
| 03 | Install prerequisite | Only when running but missing | CLI command (read-only, with copy suffix); "I've done it — Recheck" action |
| 04 | Choose / activate | Only when running and prerequisite met | `Select` or `TextInput` to pick/activate the model, instance, or resource |
| 05 | Advanced settings | Only when running | Timeouts, context window, limits, binary paths; 2–3 column grid |

Optional steps: if a service has no prerequisite step (no model to install, no binary to configure), omit steps 03 and 05 and re-number visible fieldsets accordingly.

---

## 6. Header action pattern

Every integration page header follows this layout:

```
[Refresh status]   [Primary CTA ▾ or ActionGroup]   [⋯ overflow ActionGroup]
```

### Refresh status

- Always present, always enabled
- Calls `refreshStatus()` (no side effects)
- Label: `'Refresh status'`
- Icon: `Heroicon::ArrowPathMini` (or outline equivalent)

### Primary CTA

- Label: `'Start Configure'` when not yet set up; `'Edit <Service>'` when already configured
- Opens the setup wizard modal via `configureSetupAction()`
- Shown as a primary-colored `Action` or `ActionGroup` (use `ActionGroup` when the primary action has sub-variants, e.g. "Scan QR code" vs "Pair with code")
- Disable the `ActionGroup` when connected/operational — show individual disabled actions inside it so the disabled state is discoverable

### Overflow ActionGroup (⋯)

Always contains, in this order:

1. **Test connection** — calls `testConnection()`; disabled when not operational/open
2. **Service-specific destructive action** (e.g. "Sign out session", "Reset connection") — disabled when not applicable; always requires `->requiresConfirmation()`
3. Any additional non-destructive service utilities (e.g. "Register webhook", "Try start", "Recheck binaries")

---

## 7. Status display conventions

Use `$connectionStatus` to drive all visual status indicators.

| Status value | Meaning | Pulse icon color | Badge token |
|---|---|---|---|
| `'operational'` / `'open'` / `'connected'` | Healthy | `text-emerald-500` + `animate-pulse` | `success` |
| `'degraded'` | Reachable but impaired | `text-amber-500` + `animate-pulse` | `warning` |
| `'down'` / `'close'` / `'closed'` / `'disconnected'` | Unreachable | `text-red-500` | `danger` |
| `'unknown'` / `'unconfigured'` | Not yet probed or not configured | `text-gray-400` | `gray` |

Status label copy (the text beside the pulsing icon):

| Status | Label |
|---|---|
| `operational` | `Operational` |
| `open` / `connected` | `Connected` |
| `degraded` | `Degraded` |
| `down` / `close` | `Down` / `Disconnected` |
| `unknown` | `Unknown` |
| `unconfigured` | `Not configured` |

Follow `docs/ui-copy-style.md`: impersonal voice; no *we* / *you* in headings or status messages.

---

## 8. Polling

Use Livewire polling **only during transient states** (QR code on-screen, pairing in progress, service starting up). Return `null` during stable states.

```php
public function getPollingInterval(): ?string
{
    if ($this->isConnectingAttempt()) {
        return '5s';
    }
    return null;
}
```

Apply polling in the Blade partial conditionally:

```html
<div
    @if ($this->getPollingInterval())
        wire:poll.{{ $this->getPollingInterval() }}.keep-alive="refreshStatus"
    @endif
>
```

Never poll at a fixed interval unconditionally — it wastes resources when the service is already stable.

---

## 9. Blade partials breakdown

For each integration, create the following partial files under `resources/views/filament/pages/partials/`:

| File | Purpose |
|---|---|
| `<slug>-content.blade.php` | Main content container — all 4 sections |
| `<slug>-details.blade.php` | "View details" slide-over content (config snapshot, stats) |
| `<slug>-detection-status.blade.php` | Detection / health state indicator used inside the setup wizard fieldset 01 |
| `<slug>-<feature>.blade.php` | Any additional sub-partials (e.g. allowlist, poppler guide) |

Keep sub-partials small and single-purpose. Reference them in the main content partial using `@include` or Livewire's `View::make()`. Do not embed large blocks of conditional HTML directly in a partial that already has other concerns.

---

## 10. Settings persistence

Store integration settings via Spatie Laravel Settings:

```
app/Settings/<Service>Settings.php
```

Follow the existing `OllamaSettings` pattern:

- Property defaults come from `.env` via `$casts` or a custom `fromEnv()` fallback
- Saving: call `$settings->save()` or equivalent after validating the form state
- Fallback chain: **DB settings → `.env` → hardcoded defaults**

Expose the active source on the page:

```php
public function settingsSourceLabel(): string
{
    if ($this->setupComplete) {
        return 'Setup complete';
    }
    if ($this->usingSavedSettings) {
        return 'Using saved settings';
    }
    return 'Using environment defaults';
}
```

This label appears in the status section and in the "View details" slide-over.

---

## 11. `.env` reference block

Every integration ops doc (`docs/<service>-*.md`) must include a reference table of all env vars:

| Variable | Type | Default | Description |
|---|---|---|---|
| `SERVICE_HOST` | `string` | `http://127.0.0.1:<port>` | Base URL of the service |
| `SERVICE_API_KEY` | `string` | — | Authentication key |
| `SERVICE_TIMEOUT` | `int` | `30` | HTTP timeout in seconds |
| … | | | |

Document every variable consumed by the Settings class and by any service/job that calls the integration. Include minimum and maximum values where applicable (e.g. timeout 5–600 s).

---

## 12. Accessibility and UX rules

- All icon-only CTAs (Refresh, overflow actions) must use Filament Tippy tooltips — not browser `title`. See `docs/ui-tooltips.md`.
- The "View details" slide-over inside the status section must use `->slideOver()` on the `Action`; never navigate to a separate page for integration details.
- Destructive actions (Sign out, Reset) always show `->requiresConfirmation()` with a non-generic description explaining what will be lost.
- Notification messages follow `docs/ui-copy-style.md`: impersonal, past tense for success (`'Connection verified.'`), present tense for errors (`'Cannot reach <Service>.'`).
- QR / pairing code TTL countdowns must be implemented with Alpine.js (`x-data`, `x-init`, `setInterval`) — not Livewire polling — to avoid unnecessary round-trips during the countdown animation.

---

## 13. Access control

All integration pages must use the `RequiresPrimaryHouseholdAccess` trait. Family members must **not** reach integration configuration pages. The trait handles the 403 redirect automatically; no additional gate check is needed in `mount()`.

---

## 14. Section nav registration

Register section nav items matching the Blade section IDs exactly:

```php
public function sectionNavItems(): array
{
    return [
        SectionNavItem::make('Status')->id('<slug>-status'),
        SectionNavItem::make('Configuration')->id('<slug>-config'),
        SectionNavItem::make('Pipeline / Readiness')->id('<slug>-pipeline'),
        SectionNavItem::make('Activity / History')->id('<slug>-activity'),
    ];
}
```

Use the same naming conventions as Ollama (`Status`, `Pipeline Readiness`, `Receipt & Parsing Activity`) and Evolution API (`Link device`, `Connection`, `WhatsApp LID`, `Connection history`). Label the nav items for the human-readable concern, not the technical component.

See `docs/ui-section-nav.md` for the full `HasSectionNav` contract and scroll behaviour.

---

## 15. Adding a new integration — checklist

Coming-soon placeholders only need a page class, nav parent/sort/badge, the shared coming-soon partial, destination search, and Pest access tests.

When scaffolding a **full** integration page, complete every item before opening a PR:

- [ ] `app/Filament/Pages/<Service>Page.php` — class with traits, nav registration, all required methods
- [ ] `app/Filament/Pages/Schemas/<Service>SetupForm.php` — fieldset wizard
- [ ] `app/Settings/<Service>Settings.php` — Spatie settings class
- [ ] `resources/views/filament/pages/partials/<slug>-content.blade.php` — 4 sections
- [ ] `resources/views/filament/pages/partials/<slug>-details.blade.php` — slide-over
- [ ] `resources/views/filament/pages/partials/<slug>-detection-status.blade.php` — wizard step 01
- [ ] SVG icon registered in `AppServiceProvider`
- [ ] `docs/<service>-setup.md` — ops guide with `.env` reference table
- [ ] Entry added to `docs/README.md`
- [ ] Entry added to `docs/agent-onboarding.md` (section 2 read order + section 5 Integrations)
- [ ] Pest feature test covering: page renders, connection status display, configure action opens modal, settings save persists, access control blocks family members
