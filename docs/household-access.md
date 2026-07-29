# Household access (attribution + family login)

Single-tenant hub with **household roles**: one **Primary** user owns settings; optional **Family Members** can send WhatsApp receipts and (when enabled) sign in to `/admin` with limited Finances access. Not multi-tenancy — one panel, one household.

## Source of truth

| Layer | Path |
|-------|------|
| Roles | `app/Enums/HouseholdRole.php` — `primary` \| `family_member` |
| Access helpers | `app/Support/HouseholdAccess.php` |
| Spender filter | `app/Support/DashboardSpenderScope.php` |
| WhatsApp attribution | `app/Support/InvoiceSenderAttribution.php` |
| Login sync | `app/Services/FamilyMemberLoginService.php` + `app/Observers/FamilyMemberObserver.php` |
| Primary-only gate | `app/Filament/Concerns/RequiresPrimaryHouseholdAccess.php` |
| Invoice mutate ACL | `app/Policies/InvoicePolicy.php` → `HouseholdAccess::canMutateInvoice()` |
| Family Member CRUD | `app/Filament/Resources/FamilyMembers/` (Settings; primary only) |
| Local test seed | `database/seeders/FamilyMemberLoginTestSeeder.php` (local/testing only) |
| Tests | `tests/Feature/FamilyMemberAttributionLoginTest.php`, `tests/Feature/InvoiceFamilyMemberOwnershipTest.php` |

## Roles

| Role | How set | Panel access |
|------|---------|--------------|
| **Primary** | `users.household_role = primary` (default / null treated as primary) | Full `/admin` |
| **Family member** | Linked `User` created when Family Member has **login enabled** | Finances only (see below); `canAccessPanel` requires `login_enabled` still true |

Primary-only surfaces use `RequiresPrimaryHouseholdAccess` (`canAccess` + hide nav):

- Settings: Labels, Payment Methods, Family Members
- Finances: Budgets
- Integrations: Evolution API
- Tools: Backups
- Profile: household / WhatsApp allowlist sections that are primary-only
- Global search destinations filtered for non-primary users

Family members **can** use: Home (Finance dashboard), Upload Receipts, Invoices, Service Status (read-only), Profile (own account), WhatsApp OTP login.

## Family Member model

`family_members` (Settings CRUD — primary only):

| Field | Role |
|-------|------|
| `phone` | Normalized MY WhatsApp number (unique) |
| `allowlist_enabled` | Bot contact allowlist (default on) |
| `login_enabled` | Panel login via WhatsApp OTP (default off) |
| `avatar_url`, `date_of_birth`, name/display | Bidirectional with linked login `User` when login is enabled (Family Member CRUD → User; family Edit Profile → Family Member) |

Linked user email is synthetic: `family+{id}@tido.local`. Password is random (OTP-only). Disabling login deletes the linked family-member `User`.

## Invoice attribution (`family_member_id`)

UI label: **Uploaded By**.

| Source | Attribution |
|--------|-------------|
| WhatsApp image / manual text | `InvoiceSenderAttribution::familyMemberIdForSender()` — allowlisted Family Member phone → id; Profile/primary sender → `null` |
| Receipt upload / create while logged in as family member | Forced to that user’s `family_member_id` |
| Manual create as primary | Optional select (Primary option = `null`) |

`null` = Primary household spender. Non-null = that Family Member.

## Invoice permissions

Family members may **view** all invoices (list/slide-over). They may **mutate** (edit, delete, restore, force-delete, reparse, bulk select) only invoices where `family_member_id` matches their linked member. Primary may mutate any.

Create always stamps the family member’s own id (ignores form tampering). **Uploaded By** is disabled/dehydrated for family-member sessions.

## Dashboard spender filter

Finance Home filter `spender` (`DashboardSpenderScope`):

| Value | Meaning |
|-------|---------|
| `all` | No invoice scope |
| `primary` | `family_member_id` is null (label = primary user’s name + ` (me)` when viewing as primary) |
| `family:{id}` | That Family Member |

- Primary: options = All + Primary (`… (me)`) + every member
- Family member: options = All + self (`… (me)`); default = self
- Analytics (`DashboardMonthAnalytics`) apply the scope to invoice queries/joins

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

## Agent rules

1. Gate new Settings / Tools / Integrations pages with `RequiresPrimaryHouseholdAccess` (or explicit `HouseholdAccess::isPrimary()`).
2. Attribute new WhatsApp / upload invoice creates via `InvoiceSenderAttribution` or the acting user’s `family_member_id`.
3. Invoice mutate UI must respect `HouseholdAccess::canMutateInvoice()` / `InvoicePolicy` — do not hide View for family members.
4. Do not invent Spatie roles/tenancy — household role is a column + helpers only.
5. Tests: `FamilyMember::factory()->loginEnabled()`, `Http::fake` / `Queue::fake` for OTP/WhatsApp.

## Related

- [whatsapp-bot-commands.md](whatsapp-bot-commands.md) — allowlist + attribution note
- [evolution-local-windows.md](evolution-local-windows.md) — webhook + family OTP local test
- [dashboard-views.md](dashboard-views.md) — Finance Home filters (spender)
- [active-sessions.md](active-sessions.md) — session revoke on Profile
