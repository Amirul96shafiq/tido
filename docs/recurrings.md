# Recurrings

Reminder-first tracking for bills, subscriptions, debt instalments, and transfers/investments.

## Flow

1. Primary creates a **Recurring** template (Finances → Recurrings), or duplicates an existing one via the Duplicate CTA (row Actions, Edit header, or bulk toolbar). Assigned family members can later edit their own templates; they cannot create or duplicate.
2. `recurring:generate-occurrences` creates period **occurrences** and refreshes due/overdue.
3. `recurring:send-reminders` runs **every minute** and sends **one daily summary** (Filament and/or WhatsApp) for each opted-in login user whose Profile send time has been reached. Each summary lists every eligible due/overdue item for that recipient and channel. Channel toggles stay on the Recurring template; lead days and send time live on Profile → Notifications.
4. User pays externally and logs an **Expense** (upload / WhatsApp / manual).
5. After `parsed` / `reviewed`, `RecurringMatchService` matches merchant aliases + due window (±7 days) + ownership/shared, then completes the occurrence with the expense total as `actual_amount`.
6. That expense status change also pings `ExpenseUpdated`. Due Recurrings and Recurring Month Snapshot re-query over Echo while Home is open (skip/revert/mark-paid still use the in-page `recurring-occurrences-updated` event).
7. Budget alerts continue to run from the expense path unchanged.

Expenses are never auto-created by the scheduler.

## Ownership

Same shape as budgets:

| Field | Meaning |
|-------|---------|
| `family_member_id` | `null` = Primary; set = that Family Member |
| `is_shared` | Household can see/complete; expense attribution still owns Overall burn |

Family members list every template and may edit only assigned ones (`family_member_id` match). Create and Duplicate stay Primary-only. Home Due Recurrings still scopes to owned or shared templates.

## Duplicate

Primary may duplicate a template from the list row Actions kebab, the Edit header, or bulk selection. The replica copies schedule and ownership fields, resets `starts_on` to today (and recalculates `next_due_on`), resets instalment remaining when a finite series is set, and does **not** copy occurrence history. After a single duplicate, the panel opens Edit on the replica.

## Cadence

- `frequency`: `repeating` \| `once`
- `interval_months`: 1–24 when repeating (UI: Monthly / Quarterly / Every 6 months / Yearly / Custom / Once)
- Finite series: `instalment_total` / `instalment_remaining` (PayLater, Tabung)
- Optional `goal_target_amount` for Tabung-style progress (`(prior + sum(actual)) / goal`)
- Optional `prior_contributed_amount` on **Target amount** transfers: money already saved outside tido (UI may enter a transfer count × expected amount, but only RM is stored). Do **not** include transfers already completed in tido — those count via occurrence `actual_amount`.
- On Target amount save, `instalment_total` is always `ceil(goal / expected)` and `instalment_remaining` is `total − completed/skipped occurrences − floor(prior / expected)`.

## Reminder schedule (Profile)

Each login user (Primary and family) configures recurring reminders on **Profile → Notifications → Finances**. Days Before Due and Send At sit under the Recurring Reminders toggle:

| Preference | Default | Meaning |
|------------|---------|---------|
| `notify_recurring_reminders` | on | Master toggle for that user |
| `recurring_reminder_lead_days` | 7 (0–14) | Profile slider: remind on the due day and up to N days before; overdue still reminds daily |
| `recurring_reminder_time` | 08:00 | Send once per local day at or after this time (user Profile timezone). Saving a time that is already past today skips today and waits until tomorrow. |

Per-template **In-app** / **WhatsApp** toggles still choose channels. Each daily pass sends **at most one summary per channel** (one WhatsApp message and/or one Filament inbox notification). Items are ordered overdue first, then ascending due date. Inbox severity is `danger` when any listed item is overdue, otherwise `warning`. Routing:

- **Primary** pass: all active templates → Primary inbox / Primary WhatsApp summaries
- **Family login** pass: assigned, non-shared templates only → that user’s inbox / WhatsApp summaries
- **No-login family** (phone on Family Member, login off): one WhatsApp summary of their assigned WhatsApp-enabled items, on the **Primary** clock when Primary’s toggle is on
- **Shared** templates: Primary only (family login pass skips shared)
- Channel split: WhatsApp-only templates appear only in the WhatsApp summary; inbox-only templates appear only in the Filament summary

## Commands

| Command | Schedule |
|---------|----------|
| `php artisan recurring:generate-occurrences` | Daily 00:15 |
| `php artisan recurring:send-reminders` | Every minute (per-user send time) |
| `php artisan recurring:match-expenses` | Manual (backfill) |

Use `recurring:match-expenses` after seeding recurrings (or importing historical receipts) so already-`parsed`/`reviewed` expenses can complete open occurrences. Pass `--dry-run` to preview matches without writing.

## Related

- Household visibility: [household-access.md](household-access.md)
- Agent map: [agent-onboarding.md](agent-onboarding.md)
