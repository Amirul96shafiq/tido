# Realtime broadcasting (Reverb + Echo)

Live expense tables update when a receipt is uploaded or OCR status changes, without polling those tables. The Finances Monthly Spending Overview stats, Monthly Spending Trend, Spending by Label, Budget Performance, Top Merchants, Spending by Payment Method, Receipts by Upload Source, Due Recurrings, Recurring Month Snapshot, and the Filament database-notifications inbox refresh the same way, without a 30s or 60s poll.

## Why this exists

Receipt parsing is asynchronous. An expense is created as `pending`, then `ExtractReceiptDataJob` (or a WhatsApp job) updates status later on the `receipts` queue. WhatsApp uploads also arrive from a webhook, not the current Livewire request. Budget alerts, backup notices, and manual-review notices are stored as database notifications after those jobs finish.

Expenses, Upload Receipts, and Recent Receipts used `->poll('10s.visible')` to notice table changes. The notifications slide-over used `databaseNotificationsPolling('60s')`. Polling is replaced with Laravel Reverb (local websocket) plus Filament Echo.

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

Service Status includes a Reverb health probe when `BROADCAST_CONNECTION=reverb` (`GET /apps` on the configured host/port). A 401 still counts as operational — the process answered. See [service-status.md](service-status.md).

## How it works

### Expense tables

```
Expense created, important field changed, deleted, or restored
  (ExpenseItem label / amount / quantity also pings the parent)
  → ExpenseObserver / ExpenseItemObserver
  → App\Events\ExpenseUpdated (queued on default)
  → private channel household.expenses
  → Filament EchoFactory (window.Echo)
  → ListExpenses / ReceiptUploadPage / RecentReceipts refreshExpensesTable() → resetTable()
  → MonthlySpendingOverview / BudgetStatus refreshOnExpenseBroadcast() (current month only)
  → MonthlyTrend / SpendingByLabel / TopMerchants / SpendingByPaymentMethod / ReceiptsBySource refreshFromExpenseBroadcast() → updateChartData() (current month only)
  → DueRecurrings / RecurringMonthSnapshot refreshOnExpenseBroadcast() (always; widgets show the current calendar month, not the dashboard month picker)
```

This is a refresh ping only. Payload is `{ id, status }` only. Never broadcast merchant, amounts, `raw_ai_response`, image paths, or the Eloquent model. Listeners re-query the database.

Important expense fields: `merchant_name`, `original_filename`, `status`, money columns (`subtotal`, `total_tax`, `total_amount`, `discount_total`, `rounding_amount`), `payment_method_id`, `source`, `family_member_id`, `image_path`, `date_time`. Notes, invoice number, WhatsApp ids, conversion audit columns, and `edited_by` alone do not ping.

Important item fields: `label_id`, `line_total`, `quantity`, `unit_price`, `expense_id`. Description / serial / warranty do not ping.

Channel auth: any `User` that `canAccessPanel` the admin panel (Primary and login-enabled Family Members). Guests and users without panel access are denied.

Pending rows use a CSS pulse (`.tido-expense-status-pending`) for “currently parsing”. There is no mid-OCR progress event.

### Database notifications

```
Notification::make()->sendToDatabase($user)
  → App\Filament\Notifications\Notification (always isEventDispatched: true)
  → Filament\Notifications\Events\DatabaseNotificationsSent (queued)
  → private channel App.Models.User.{id}
  → Echo .database-notifications.sent
  → DatabaseNotifications $refresh (500ms)
```

This is a refresh ping only. The event payload is empty. Never broadcast title, body, or notification data. The inbox still reads from the `notifications` table. This is not Filament flash toast broadcasting (`->broadcast()`).

Channel auth: the signed-in `User` may subscribe only to their own `App.Models.User.{id}` channel. Guests and other users’ channels are denied.

`databaseNotificationsPolling` is `null`. There is no poll fallback. Queue + Reverb must be running (`npm run dev:full` already starts both). The Echo listener stays mounted when the inbox is empty so the first notification can refresh the empty state.

## Filament Echo (no extra npm Echo package)

The panel already loads EchoFactory via `@filamentScripts`. Echo is created when `config('filament.broadcasting.echo')` is set:

[`config/filament.php`](../config/filament.php) contains **only** that Echo client array. Panel / theme / navigation stay in [`AdminPanelProvider`](../app/Providers/Filament/AdminPanelProvider.php). Do not publish a full Filament panel config.

Do not add `laravel-echo` or `pusher-js` npm packages for the panel. Do not import `resources/js/echo.js` into Vite.

## Attach a later listener

Shared traits:

- Tables: `App\Filament\Concerns\RefreshesTableOnExpenseBroadcast` (`resetTable()`)
- Stats / custom widgets: `App\Filament\Concerns\RefreshesOnExpenseBroadcast` (Livewire re-render; skip when a past month is selected). Chart widgets should override `refreshFromExpenseBroadcast()` to call `updateChartData()`.

**Live listeners**

- [`ListExpenses`](../app/Filament/Resources/Expenses/Pages/ListExpenses.php)
- [`ReceiptUploadPage`](../app/Filament/Pages/ReceiptUploadPage.php) recent-uploads table
- [`RecentReceipts`](../app/Filament/Widgets/RecentReceipts.php) dashboard widget
- [`MonthlySpendingOverview`](../app/Filament/Widgets/MonthlySpendingOverview.php) Finances stats
- [`MonthlyTrend`](../app/Filament/Widgets/MonthlyTrend.php) Finances chart (`updateChartData()`)
- [`SpendingByLabel`](../app/Filament/Widgets/SpendingByLabel.php) Finances chart (`updateChartData()`)
- [`BudgetStatus`](../app/Filament/Widgets/BudgetStatus.php) Budget Performance
- [`TopMerchants`](../app/Filament/Widgets/TopMerchants.php) Finances chart (`updateChartData()`)
- [`SpendingByPaymentMethod`](../app/Filament/Widgets/SpendingByPaymentMethod.php) Finances chart (`updateChartData()`)
- [`ReceiptsBySource`](../app/Filament/Widgets/ReceiptsBySource.php) Finances chart (`updateChartData()`)
- [`DueRecurrings`](../app/Filament/Widgets/DueRecurrings.php) Due Recurrings (OCR match while Home is open; skip/revert/mark-paid still dispatch `recurring-occurrences-updated`)
- [`RecurringMonthSnapshot`](../app/Filament/Widgets/RecurringMonthSnapshot.php) Bills · month snapshot (same Echo ping; keeps `recurring-occurrences-updated` for in-page skip/revert)
- [`DatabaseNotifications`](../app/Filament/Livewire/DatabaseNotifications.php) inbox (Filament `.database-notifications.sent` on `App.Models.User.{id}`)

To attach another table:

1. `use RefreshesTableOnExpenseBroadcast` on the Livewire class that owns the table
2. Remove `->poll('10s.visible')` from that table
3. If the class already defines `getListeners()`, merge `...$this->expenseBroadcastListeners()` instead of using the trait’s `getListeners()`
4. Add/adjust a Pest assertion that the Echo listener key is present and `wire:poll.10s.visible` is gone

To attach another stats or custom widget:

1. `use RefreshesOnExpenseBroadcast` on the widget
2. Return `null` from `getPollingInterval()` / drop `wire:poll`
3. Chart widgets: override `refreshFromExpenseBroadcast()` to `updateChartData()`
4. Add/adjust a Pest assertion that the Echo listener key is present and `wire:poll.30s` is gone

Planned follow-ups (not in this change):

- Current Currency is out of scope (FX cache, not expenses)

## Tests

- `Event::fake([ExpenseUpdated::class])` so Eloquent observers still run
- `Event::fake([DatabaseNotificationsSent::class])` for inbox ping dispatch (partial fakes must not swallow the other event)
- Channel tests via `POST /broadcasting/auth` with `postJson`, switching the test to the `pusher` driver so channel callbacks actually run (`null`/`log` skip auth). Never start Reverb.
- `BROADCAST_CONNECTION=null` in phpunit — never boot Reverb in CI

```bash
php artisan test --compact --filter=ExpenseUpdatedBroadcastTest
php artisan test --compact --filter=HouseholdExpensesChannelTest
php artisan test --compact --filter=DatabaseNotificationsBroadcastTest
php artisan test --compact --filter=UserNotificationsChannelTest
php artisan test --compact --filter=LiveTableFiltersTest
php artisan test --compact --filter=ReceiptUploadPageTest
php artisan test --compact --filter=RecentReceiptsWidgetTest
php artisan test --compact --filter=MonthlySpendingOverviewForecastTest
php artisan test --compact --filter=MonthlyTrendWidgetTest
php artisan test --compact --filter=SpendingByLabelWidgetTest
php artisan test --compact --filter=BudgetStatusWidgetTest
php artisan test --compact --filter=TopMerchantsWidgetTest
php artisan test --compact --filter=SpendingByPaymentMethodWidgetTest
php artisan test --compact --filter=ReceiptsBySourceWidgetTest
php artisan test --compact --filter=DueRecurringsWidgetTest
php artisan test --compact --filter=RecurringMonthSnapshotWidgetTest
php artisan test --compact --filter=WebAppLoadPerformanceTest
php artisan test --compact --filter=ServiceHealthTest
php artisan test --compact --filter=ServiceStatusPageTest
```

## LAN / phone

Phone browsers that load `/admin` also need the Reverb websocket. Allow inbound TCP **8081** on the LAN (same subnet as 80 and 5173). Set `REVERB_HOST` to a hostname or LAN IP the phone can resolve if `localhost` is not reachable from the device.

## Agent rules

1. Side effects stay in `ExpenseObserver` / `ExpenseItemObserver` — do not dispatch `ExpenseUpdated` from Filament resources
2. Keep the expense broadcast payload to `id` + `status`. Ping on create, important field changes, delete/restore, and item label/amount changes — not notes or AI payload.
3. New Echo table listeners should reuse `RefreshesTableOnExpenseBroadcast`. New Echo stats/custom widget listeners should reuse `RefreshesOnExpenseBroadcast`
4. Database inbox pings stay on Filament `DatabaseNotificationsSent` via `App\Filament\Notifications\Notification::sendToDatabase()` — do not invent a second event or pass `isEventDispatched: false` expecting it to stick
5. Do not add Pusher Cloud or a second websocket server
6. Do not hit live Reverb, Ollama, or Evolution in tests
