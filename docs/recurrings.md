# Recurrings

Reminder-first tracking for bills, subscriptions, debt instalments, and transfers/investments.

## Flow

1. Primary creates a **Recurring** template (Finances → Recurrings), or duplicates an existing one via the Duplicate CTA (row Actions, Edit header, or bulk toolbar). Assigned family members can later edit their own templates; they cannot create or duplicate.
2. `recurring:generate-occurrences` creates period **occurrences** and refreshes due/overdue.
3. `recurring:send-reminders` sends Filament + WhatsApp nudges (Primary always; assigned family member when set; shared → Primary only).
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
- Optional `goal_target_amount` for Tabung-style progress (`sum(actual) / goal`)

## Commands

| Command | Schedule |
|---------|----------|
| `php artisan recurring:generate-occurrences` | Daily 00:15 |
| `php artisan recurring:send-reminders` | Daily 08:00 |
| `php artisan recurring:match-expenses` | Manual (backfill) |

Use `recurring:match-expenses` after seeding recurrings (or importing historical receipts) so already-`parsed`/`reviewed` expenses can complete open occurrences. Pass `--dry-run` to preview matches without writing.

## Related

- Household visibility: [household-access.md](household-access.md)
- Agent map: [agent-onboarding.md](agent-onboarding.md)
