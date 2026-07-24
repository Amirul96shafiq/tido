# Active Sessions

Profile **Active Sessions** (`/admin/profile#active-sessions`) lists every Laravel session row for the signed-in user, shows device/browser details parsed from the user agent, and lets the user revoke other sessions (sign out remote browsers).

## Source of truth

| Layer | Path |
|-------|------|
| Filament UI | `app/Filament/Pages/Auth/EditProfile.php` — `Section::make('Active Sessions')->id('active-sessions')` + `EmbeddedTable` |
| Service | `app/Services/ActiveSessionService.php` |
| DTO | `app/Services/ActiveSessionData.php` |
| User-agent parsing | `app/Support/UserAgentDevice.php` |
| Login stamp hook | `app/Filament/Pages/Auth/Login.php` → `scheduleSessionCreatedAtStamp()` |
| Migration | `database/migrations/2026_07_25_012749_add_created_at_to_sessions_table.php` |
| Global search | `app/Filament/GlobalSearch/AdminDestinationSearch.php` — `Active Sessions` section |
| Tests | `tests/Feature/ActiveSessionsTest.php`, `tests/Feature/GlobalSearchTest.php` |

## Data model

Uses Laravel’s built-in `sessions` table (`config/session.php` → `database` driver). Relevant columns:

| Column | Use |
|--------|-----|
| `id` | Session ID (primary key) |
| `user_id` | Owner — set when the user authenticates |
| `ip_address` | Shown in device detail (`Chrome on Windows · 192.168.1.10`) |
| `user_agent` | Parsed by `UserAgentDevice` |
| `last_activity` | Unix timestamp; Laravel’s session GC uses this |
| `created_at` | Unix timestamp — **custom** column added by tido (nullable); when the session was first seen |

There is no Eloquent model. `ActiveSessionService` reads/writes via `DB::table('sessions')`.

## Session `created_at` stamping

Laravel does not populate `created_at` on session rows. tido backfills it on first touch:

1. **Login** — `Login::scheduleSessionCreatedAtStamp()` runs `stampCreatedAt()` in an `app()->terminating()` callback so the session row exists before the update.
2. **Profile mount** — `EditProfile::mount()` stamps the current session when the profile page loads.
3. **List** — `ActiveSessionService::listFor()` stamps the current session before querying.

`stampCreatedAt()` only updates rows where `created_at` is `null` (idempotent). For legacy rows still missing `created_at`, display falls back to `last_activity`.

Timestamps are converted to `Carbon` in the user’s timezone (`User::$timezone`, else `config('app.timezone')`).

## Device display

`UserAgentDevice::parse($userAgent)` returns:

| Field | Values |
|-------|--------|
| `deviceClass` | `Web` or `Mobile Web` (regex on Mobile/Android/iPhone/etc.) |
| `browser` | Chrome, Firefox, Safari, Edge, Opera, or `Unknown browser` |
| `os` | Windows, macOS, Linux, Android, iOS, etc. |

`detail($ipAddress)` builds the table description, e.g. `Chrome on Windows · 192.168.1.10`.

## Filament table pattern

Edit Profile uses `InteractsWithTable` with a **records-based** table (not an Eloquent query):

```php
EmbeddedTable::make()->columnSpanFull()   // inside the Active Sessions section schema

public function table(Table $table): Table
{
    return $table
        ->queryStringIdentifier('activeSessions')
        ->records(fn (): array => app(ActiveSessionService::class)->recordsForTable(...))
        // columns, recordActions, empty state...
}
```

| Column | Behavior |
|--------|----------|
| Device | `device_class` label + `device_detail` description |
| Current Session | Primary badge “This device” when `is_current` |
| Created At | `->since()->dateTimeTooltip()` (relative + full datetime on hover) |

| Action | Behavior |
|--------|----------|
| Revoke | Danger button, confirmation modal; hidden for current session; deletes the `sessions` row |

After revoke: `resetTable()` + success notification (“Session revoked”).

Use `->modifyUngroupedRecordActionsUsing(fn (Action $action): Action => $action->button())` so Revoke renders as a button (not icon-only) inside the embedded profile table.

## Revoke rules

`ActiveSessionService::revoke($user, $sessionId, $currentSessionId)`:

- Throws `InvalidArgumentException` if `$sessionId === $currentSessionId`.
- Deletes only when `id` **and** `user_id` match — cannot revoke another user’s session.

Deleting the row invalidates that session on the next request (Laravel cannot find the session payload).

## Global search

Registered in `AdminDestinationSearch` under group **Sections**:

- Title: `Active Sessions`
- URL: profile URL + `#active-sessions`
- Keywords: `active`, `sessions`, `devices`, `revoke`, `logout`, `browser`

The section must keep `->id('active-sessions')` so hash navigation, the sticky profile section tabs, and `<x-hash-scroll />` work in SPA mode. See [`ui-profile-section-nav.md`](ui-profile-section-nav.md).

## Agent rules

1. Keep session logic in `ActiveSessionService` — do not query `sessions` directly from Filament pages except via the service.
2. Do not add an Eloquent `Session` model unless there is a broader refactor; the table is framework-owned.
3. When adding new session metadata, prefer extra columns on `sessions` with a migration (specify all attributes if altering existing columns).
4. Always scope reads/deletes by `user_id` — single-tenant but still per-user sessions.
5. New profile sections: stable `->id('kebab-case')` + register in `AdminDestinationSearch` if searchable.
6. Tests: insert rows with `DB::table('sessions')->insert()` / `updateOrInsert`; use `TestAction::make('revoke')->table($sessionId)` for Filament table actions. See `insertActiveSession()` helper in `ActiveSessionsTest.php`.
7. UI copy: impersonal voice — see [ui-copy-style.md](ui-copy-style.md) (“This device”, not “Your device”).
8. Created-at columns elsewhere: use `->since()->dateTimeTooltip()` per Filament conventions.

## Related

- Profile sticky actions + blur: [ui-sticky-blur.md](ui-sticky-blur.md)
- Global search section anchors: `.cursor/rules/filament-conventions.mdc` (Global search)
- Hash scroll for `#active-sessions`: `<x-hash-scroll />` in panel layout
