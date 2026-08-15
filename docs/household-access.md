# Household access (attribution + family login)

Single-tenant hub with **household roles**: one **Primary** user owns settings; optional **Family Members** can send WhatsApp receipts and (when enabled) sign in to `/admin` with limited Finances access. Not multi-tenancy — one panel, one household.

## Source of truth

| Layer | Path |
|-------|------|
| Roles | `app/Enums/HouseholdRole.php` — `primary` \| `family_member` |
| Access helpers | `app/Support/HouseholdAccess.php` |
| Spender filter | `app/Support/DashboardSpenderScope.php` |
| WhatsApp attribution | `app/Support/ExpenseSenderAttribution.php` |
| WhatsApp LID mapping | `app/Support/WhatsAppLid.php` — links opaque `@lid` identities to allowlisted contacts |
| Login sync | `app/Services/FamilyMemberLoginService.php` + `app/Observers/FamilyMemberObserver.php` |
| Primary-only gate | `app/Filament/Concerns/RequiresPrimaryHouseholdAccess.php` |
| Expense mutate ACL | `app/Policies/ExpensePolicy.php` → `HouseholdAccess::canMutateExpense()` |
| Budget mutate ACL | `app/Policies/BudgetPolicy.php` → `HouseholdAccess::canMutateBudget()` |
| Recurring mutate ACL | `app/Policies/RecurringPolicy.php` → `HouseholdAccess::canMutateRecurring()` |
| Resource edit audit | `app/Models/Concerns/TracksResourceEdits.php` → `edited_by` on supported resource tables |
| Account switching | `app/Filament/Livewire/AccountSwitcher.php` + `resources/views/filament/livewire/account-switcher.blade.php` |
| Family Member CRUD | `app/Filament/Resources/FamilyMembers/` (Settings; primary only) |
| Local test seed | `database/seeders/FamilyMemberLoginTestSeeder.php` (local/testing only) |
| Tests | `tests/Feature/FamilyMemberAttributionLoginTest.php`, `tests/Feature/ExpenseFamilyMemberOwnershipTest.php`, `tests/Feature/BudgetFamilyMemberOwnershipTest.php`, `tests/Feature/RecurringFamilyMemberOwnershipTest.php` |

## Roles

| Role | How set | Panel access |
|------|---------|--------------|
| **Primary** | `users.household_role = primary` (default / null treated as primary) | Full `/admin` |
| **Family member** | Linked `User` created when Family Member has **login enabled** | Finances only (see below); `canAccessPanel` requires `login_enabled` still true |

Primary-only surfaces use `RequiresPrimaryHouseholdAccess` (`canAccess` + hide nav):

- Settings: Labels, Payment Methods, Family Members
- Integrations: Evolution API
- Tools: Backups
- Profile: household / WhatsApp allowlist sections that are primary-only
- Global search destinations filtered for non-primary users (Budgets and Recurrings stay visible)

Family members **can** use: Home (Finance dashboard), Upload Receipts, Expenses, Budgets, Recurrings, Service Status (read-only), Profile (own account), WhatsApp OTP login.

## Budgets (owner + share)

Each budget has:

| Field | Meaning |
|-------|---------|
| `family_member_id` | Owner. `null` = Primary; non-null = that Family Member |
| `is_shared` | Spending pool. `false` = only the owner’s expenses count; `true` = all household expenses count |

**List / View:** Family members see every household budget (same as Expenses). View slide-over stays available for records they cannot edit.

**Mutate:** Family members may edit, delete, or toggle `is_active` only when `family_member_id` matches their linked member. Shared-but-not-owned is view-only. Assignment (`family_member_id`, `is_shared`) is locked on family edit. Primary may mutate any.

**Create:** Primary only. Family members still see New Budget; the control is disabled with `Only {primaryUsername} able to use this CTA button.` Direct create URLs stay forbidden.

**Visibility (Home Budget Performance):** Primary sees all active budgets. Family members see budgets they own **or** budgets with `is_shared = true`. Assigned rows link to edit. Reorder stays primary-only.

**Alerts:** Primary always receives WhatsApp/Filament alerts when enabled on the budget. If the budget is assigned to a Family Member, that member’s WhatsApp number is also notified; their linked login user receives Filament alerts only when login is enabled and `notify_budget_alerts` is on.

Existing budgets migrated with `is_shared = true` (household pool). New budgets default to personal (`is_shared = false`) assigned to Primary.

## Recurrings (owner + share)

Ownership fields match budgets (`family_member_id`, `is_shared`). Full behaviour: [recurrings.md](recurrings.md).

**List / View / Mutate / Create:** Same ACL as budgets — family members list every template, edit only assigned ones, and cannot create (New Recurring stays visible but disabled).

**Visibility (Home Due Recurrings / month bills snapshot):** Primary sees all open due/overdue occurrences. Family members see occurrences for templates they own **or** `is_shared = true` (Skip allowed on the dues list). Assigned rows link to edit. Manage opens the Recurrings list. Reorder stays primary-only.

## Family Member model

`family_members` (Settings CRUD — primary only):

| Field | Role |
|-------|------|
| `phone` | Normalized MY WhatsApp number (unique) |
| `whatsapp_lid` | Optional normalized WhatsApp Linked ID (unique); populated from the Evolution API LID linking flow |
| `allowlist_enabled` | Bot contact allowlist (default on) |
| `login_enabled` | Panel login via WhatsApp OTP (default off) |
| `avatar_url`, `date_of_birth`, name/display | Bidirectional with linked login `User` when login is enabled (Family Member CRUD → User; family Edit Profile → Family Member) |

Linked user email is synthetic: `family+{id}@tido.local`. Password is random (OTP-only). Disabling login deletes the linked family-member `User`.

## Expense attribution (`family_member_id`)

UI label: **Uploaded By**.

| Source | Attribution |
|--------|-------------|
| WhatsApp image / PDF / manual text | `ExpenseSenderAttribution::familyMemberIdForSender()` — allowlisted Family Member phone or linked LID → id; Profile/primary sender → `null` |
| Receipt upload / create while logged in as family member | Forced to that user’s `family_member_id` |
| Manual create as primary | Optional select (Primary option = `null`) |

`null` = Primary household spender. Non-null = that Family Member.

## Expense permissions

Family members may **view** all expenses (list/slide-over). They may **mutate** (edit, delete, restore, force-delete, reparse, bulk select) only expenses where `family_member_id` matches their linked member. Primary may mutate any.

In the Expenses table, only expenses the current user may mutate receive a row edit link. Non-owned expense cells are rendered without an edit URL, while the read-only View slide-over remains available for all expenses.

Create always stamps the family member’s own id (ignores form tampering). **Uploaded By** is disabled/dehydrated for family-member sessions.

## Resource edit attribution

The resource edit audit records the authenticated account that last created or updated a `Backup`, `Budget`, `FamilyMember`, `Expense`, `Label`, or `PaymentMethod`. Primary and Family Member accounts are both valid editors when the relevant policy permits the mutation; an unauthenticated system update clears `edited_by` rather than attributing the change to a stale user.

The resource tables show the editor’s username as `User.display_name`, falling back to `User.name`. This is separate from expense **Uploaded By**, which describes the household spender/source through `Expense.family_member_id`, and it does not change expense authorization.

## WhatsApp LID identities

WhatsApp may identify a chat with a Linked ID (`@lid`) instead of a phone-number JID. LIDs are opaque identifiers and cannot be normalized as Malaysian phone numbers. A linked LID is stored on either `users.whatsapp_lid` (Primary) or `family_members.whatsapp_lid` (Family Member), and inbound messages resolve to the existing allowlisted phone before bot routing and expense attribution.

An unlinked LID is ignored by the webhook and remembered as a pending identity, including its optional push name, for up to 30 days. A Primary user can open **Integrations → Evolution API → WhatsApp LID**, link it to the Primary contact or an allowlisted Family Member, or dismiss it. Unlinking removes the mapping and causes later messages from that LID to become pending again.

## Dashboard spender filter

Finance Home filter `spender` (`DashboardSpenderScope`):

| Value | Meaning |
|-------|---------|
| `all` | No expense scope |
| `primary` | `family_member_id` is null (label = primary user’s name + ` (me)` when viewing as primary) |
| `family:{id}` | That Family Member |

- Primary: options = All + Primary (`… (me)`) + every member; default = Primary (self)
- Family member: options = All + self (`… (me)`); default = self
- Analytics (`DashboardMonthAnalytics`) apply the scope to expense queries/joins

## Login

1. Primary enables **Allow panel login via WhatsApp OTP** on the Family Member.
2. Member opens `/admin/login`, enters their WhatsApp number, receives OTP (Evolution).
3. Email/password login remains **primary only**.

**Local / testing without a second phone:**

```env
WHATSAPP_LOGIN_DEV_OTP=123456
WHATSAPP_LOGIN_DEV_PHONES=60111222333
```

`DatabaseSeeder` / `FamilyMemberLoginTestSeeder` seeds **Sample Spouse** on that number with login + allowlist. OTP form accepts the fixed code (no Evolution send). See [evolution-local-windows.md](evolution-local-windows.md).

## Account switching

Primary accounts with at least one login-enabled Family Member can open **Swap Account** from the Filament user menu. A Family Member is switchable only when `login_enabled` is true and a linked login `User` exists.

The compact switcher previews up to two eligible Family Members for a Primary account. While switched into a Family Member account, it previews one other eligible Family Member alongside the switch-back row. **View All Family Members** opens the complete eligible list. Each switch requires confirmation. During a switch, the application signs in as the linked Family Member account and retains the original Primary account in the session, so normal Family Member panel access and expense mutation rules continue to apply.

While switched into a Family Member account, the switcher keeps the original Primary account available as **Switch back** and permits switching to another eligible Family Member. **Switch back** restores the original Primary account and clears the temporary switching marker. A Family Member who signs in normally does not see the switcher.

Account rows use `display_name`, falling back to `name`, and display the current profile avatar when available. Saving Family Member profile changes refreshes the account-switcher name and avatar without requiring a separate page reload.

## Agent rules

1. Gate new Settings / Tools / Integrations pages with `RequiresPrimaryHouseholdAccess` (or explicit `HouseholdAccess::isPrimary()`).
2. Attribute new WhatsApp image/PDF/text and upload expense creates via `ExpenseSenderAttribution` or the acting user’s `family_member_id`.
3. Expense, Budget, and Recurring mutate UI must respect `HouseholdAccess::canMutateExpense()` / `canMutateBudget()` / `canMutateRecurring()` and the matching policies — do not hide View for family members. Create stays primary-only (visible disabled CTA).
4. Do not invent Spatie roles/tenancy — household role is a column + helpers only.
5. Tests: `FamilyMember::factory()->loginEnabled()`, `Http::fake` / `Queue::fake` for OTP/WhatsApp.
6. Treat a WhatsApp LID as unresolved until `WhatsAppLid` maps it to an allowlisted contact; never use the raw LID as a phone number.
7. Keep resource edit attribution separate from household spender attribution; use `TracksResourceEdits` for supported model changes and `HouseholdAccess` / resource policies for authorization.

## Related

- [whatsapp-bot-commands.md](whatsapp-bot-commands.md) — allowlist + attribution note
- [evolution-local-windows.md](evolution-local-windows.md) — webhook + family OTP local test
- [dashboard-views.md](dashboard-views.md) — Finance Home filters (spender)
- [active-sessions.md](active-sessions.md) — session revoke on Profile
