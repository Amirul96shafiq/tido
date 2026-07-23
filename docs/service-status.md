# Service Status

Filament **Tools → Service Status** (`/admin/service-status`) monitors critical dependencies with OpenAI-style uptime bars, a summary report, and stored probe history.

## Source of truth

| Layer | Path |
|-------|------|
| Filament page | `app/Filament/Pages/ServiceStatusPage.php` |
| Blade UI | `resources/views/filament/pages/service-status.blade.php` |
| Aggregator | `app/Services/Health/ServiceHealthAggregator.php` |
| Recorder | `app/Services/Health/ServiceHealthRecorder.php` |
| Probes | `app/Services/Health/Probes/*` |
| Enums | `app/Enums/MonitoredService.php`, `app/Enums/ServiceHealthStatus.php` |
| Model / table | `app/Models/ServiceHealthSample.php` → `service_health_samples` |
| Commands | `health:probe`, `health:prune` (`app/Console/Commands/`) |
| Schedule | `routes/console.php` — probe every 15m, prune daily at 04:00 |

## Monitored services

| Key | Label | When included |
|-----|-------|----------------|
| `app` | Application | Always |
| `database` | Database | Always |
| `ollama` | Ollama | Always (`GET {OLLAMA_HOST}/api/tags`) |
| `evolution` | Evolution API | Always (`EvolutionInstanceService::connectionState()`) |
| `queue` | Queue | Always (DB connection + failed-job threshold; Redis ping when driver is `redis`) |
| `google_drive` | Google Drive | Only when `filesystems.disks.google` credentials are configured |

Status values: `operational`, `degraded`, `down` (UI-only `unknown` for empty history pieces).

## UI layout

Two-column page (stacks on small screens):

| Column | Content |
|--------|---------|
| Left (40%) | **Summary report (date)** — Evolution “Link device”-style status card, monitored/operational/degraded/down counts |
| Right (60%) | **System status (date)** — per-service current status, uptime %, 30-day barcode (60 × 12h pieces) |

Section titles use dashboard widget date format: `Summary report (24 Jun 2026 – 23 Jul 2026)` — no separate description line.

**Bar colors:** operational = `emerald` (not Filament `success`, which is orange in tido). Degraded = warning. Down = danger. Empty = gray.

**Piece tooltips:** HTML via Tippy (`allowHTML: true`), one line each for date, time window, status, detail. Mobile: segments use `data-tippy-mobile` — tap to open (see [ui-tooltips.md](ui-tooltips.md)).

**Header action:** Run check now — calls `ServiceHealthRecorder::recordAll()` and refreshes the report.

## Aggregation rules

- **Visible window:** last 30 calendar days (60 pieces: two 12-hour blocks per day, app/user timezone).
- **Retention:** raw samples 30 days (`health:prune`).
- **Uptime %:** operational samples ÷ all samples in the window (one decimal).
- **Historical piece color:** worst status in that 12h window.
- **Current in-progress piece** (ends in the future): latest sample status + message (so a recovered service shows green while uptime % still reflects earlier failures).
- **Summary banner:** worst *current* status across configured services.

## Scheduler & manual use

```bash
php artisan health:probe    # record samples now
php artisan health:prune    # drop samples older than 30 days (override: --days=)
```

Requires `schedule:run` (or `npm run dev:full`) for automatic polling.

## Agent rules

1. Nav: **Tools** group, sort after Backups — do not move to Integrations.
2. Add new probes under `app/Services/Health/Probes/` implementing `ServiceHealthProbe`; register in `ServiceHealthRecorder`.
3. Extend `MonitoredService` enum for new keys; gate with `isConfigured()` when optional.
4. Keep Filament page thin — aggregation/probes stay in `app/Services/Health/`.
5. Tests: `Http::fake()` for Ollama/Evolution; never hit real services. See `tests/Feature/ServiceHealthTest.php`, `ServiceStatusPageTest.php`.
6. Operational UI must use `ServiceHealthStatus::barColorClass()` / `iconColorClass()` (emerald), not `success` tokens.
7. Mobile tooltips: add `data-tippy-mobile` on new status-bar segments if they need tap tooltips below `sm`.

## Related

- Evolution connection UI (separate from health probes): [evolution-local-windows.md](evolution-local-windows.md)
- Mobile Tippy exception: [ui-tooltips.md](ui-tooltips.md)
- Architecture monitoring overview: [system-architecture.md](system-architecture.md) §6.5
- Backups (sibling Tools item): [backups-and-danger-zone.md](backups-and-danger-zone.md)
