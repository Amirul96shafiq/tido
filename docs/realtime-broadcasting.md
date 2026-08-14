# Realtime broadcasting (Reverb + Echo)

Live expense tables update when a receipt is uploaded or OCR status changes, without polling those tables.

## Why this exists

Receipt parsing is asynchronous. An expense is created as `pending`, then `ExtractReceiptDataJob` (or a WhatsApp job) updates status later on the `receipts` queue. WhatsApp uploads also arrive from a webhook, not the current Livewire request.

Expenses, Upload Receipts, and Recent Receipts used `->poll('10s.visible')` to notice those changes. Polling is replaced with Laravel Reverb (local websocket) plus Filament Echo: those tables refresh only when an expense is created or its status changes.

## Local runtime

Reverb is a native Windows host process, like Ollama and Evolution. It is **not** Docker.

| Service | Port | Notes |
|---------|------|--------|
| tido (`artisan serve`) | 2000 | Mapped from 80 via portproxy |
| Vite | 5173 | HMR |
| Evolution | **8080** | WhatsApp |
| **Reverb** | **8081** | Must not use 8080 |

`npm run dev:full`, `dev:whatsapp`, and `dev:all` start `php artisan reverb:start`.

Required `.env` (see `.env.example`):

```
BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=8081
REVERB_SERVER_PORT=8081
REVERB_SCHEME=http
```

`REVERB_APP_ID` / `KEY` / `SECRET` are local identifiers, not third-party Pusher credentials. Do not use Pusher Cloud.

phpunit keeps `BROADCAST_CONNECTION=null`. Tests must not start a websocket or hit live Reverb.

## How it works

```
Expense created or status changed
  → ExpenseObserver
  → App\Events\ExpenseUpdated (queued on default)
  → private channel household.expenses
  → Filament EchoFactory (window.Echo)
  → ListExpenses / ReceiptUploadPage / RecentReceipts refreshExpensesTable() → resetTable()
```

Payload is `{ id, status }` only. Never broadcast `raw_ai_response`, image paths, or the Eloquent model.

Channel auth: any `User` that `canAccessPanel` the admin panel (Primary and login-enabled Family Members). Guests and users without panel access are denied.

Pending rows use a CSS pulse (`.tido-expense-status-pending`) for “currently parsing”. There is no mid-OCR progress event.

## Filament Echo (no extra npm Echo package)

The panel already loads EchoFactory via `@filamentScripts`. Echo is created when `config('filament.broadcasting.echo')` is set:

[`config/filament.php`](../config/filament.php) contains **only** that Echo client array. Panel / theme / navigation stay in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php). Do not publish a full Filament panel config.

Do not add `laravel-echo` or `pusher-js` npm packages for the panel. Do not import `resources/js/echo.js` into Vite.

## Attach a later listener

Shared trait: `App\Filament\Concerns\RefreshesTableOnExpenseBroadcast`.

**Live listeners**

- [`ListExpenses`](../app/Filament/Resources/Expenses/Pages/ListExpenses.php)
- [`ReceiptUploadPage`](../app/Filament/Pages/ReceiptUploadPage.php) recent-uploads table
- [`RecentReceipts`](../app/Filament/Widgets/RecentReceipts.php) dashboard widget

To attach another table:

1. `use RefreshesTableOnExpenseBroadcast` on the Livewire class that owns the table
2. Remove `->poll('10s.visible')` from that table
3. If the class already defines `getListeners()`, merge `...$this->expenseBroadcastListeners()` instead of using the trait’s `getListeners()`
4. Add/adjust a Pest assertion that the Echo listener key is present and `wire:poll.10s.visible` is gone

Planned follow-ups (not in this change):

- Other dashboard widget polls (charts / stats at `30s`)
- Database notifications `60s` poll → Echo (the notifications Blade already listens for `.database-notifications.sent` once Echo exists)
- Service Status Reverb health probe

## Tests

- `Event::fake([ExpenseUpdated::class])` so Eloquent observers still run
- Channel tests via `POST /broadcasting/auth` with `postJson`, switching the test to the `pusher` driver so channel callbacks actually run (`null`/`log` skip auth). Never start Reverb.
- `BROADCAST_CONNECTION=null` in phpunit — never boot Reverb in CI

```bash
php artisan test --compact --filter=ExpenseUpdatedBroadcastTest
php artisan test --compact --filter=HouseholdExpensesChannelTest
php artisan test --compact --filter=LiveTableFiltersTest
php artisan test --compact --filter=ReceiptUploadPageTest
php artisan test --compact --filter=RecentReceiptsWidgetTest
```

## LAN / phone

Phone browsers that load `/admin` also need the Reverb websocket. Allow inbound TCP **8081** on the LAN (same subnet as 80 and 5173). Set `REVERB_HOST` to a hostname or LAN IP the phone can resolve if `localhost` is not reachable from the device.

## Agent rules

1. Side effects stay in `ExpenseObserver` — do not dispatch `ExpenseUpdated` from Filament resources
2. Keep the broadcast payload to `id` + `status`
3. New Echo table listeners should reuse `RefreshesTableOnExpenseBroadcast`
4. Do not add Pusher Cloud or a second websocket server
5. Do not hit live Reverb, Ollama, or Evolution in tests
