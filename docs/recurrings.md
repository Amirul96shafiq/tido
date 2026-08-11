# Recurrings

Reminder-first tracking for bills, subscriptions, debt instalments, and transfers/investments.

## Flow

1. Primary creates a **Recurring** template (Finances → Recurrings).
2. `recurring:generate-occurrences` creates period **occurrences** and refreshes due/overdue.
3. `recurring:send-reminders` sends Filament + WhatsApp nudges (Primary always; assigned family member when set; shared → Primary only).
4. User pays externally and logs an **Expense** (upload / WhatsApp / manual).
5. After `parsed` / `reviewed`, `RecurringMatchService` matches merchant aliases + due window (±7 days) + ownership/shared, then completes the occurrence with the expense total as `actual_amount`.
6. Budget alerts continue to run from the expense path unchanged.

Expenses are never auto-created by the scheduler.

## Ownership

Same shape as budgets:

| Field | Meaning |
|-------|---------|
| `family_member_id` | `null` = Primary; set = that Family Member |
| `is_shared` | Household can see/complete; expense attribution still owns Overall burn |

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

## Related

- Household visibility: [household-access.md](household-access.md)
- Agent map: [agent-onboarding.md](agent-onboarding.md)
