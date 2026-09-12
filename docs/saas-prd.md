# SaaS PRD — Future Product (Companion)

> **Status:** Companion PRD + phased implementation  
> Tenancy kernel, Register, and related schema follow [multi-household-change-checklist.md](multi-household-change-checklist.md) (`MH-*`, one item at a time) after [system-architecture.md](system-architecture.md) authorizes the tenancy phase (**MH-002**).  
> **Free/Pro billing remains unauthorized** until an explicit later phase. Do not implement billing, quotas, or Stripe from this file.

Product name remains **tido**. Expense tags remain **Label** / **Labels** (never Category).

---

## 1. Problem

**Today (tenancy kernel shipped):** tido supports **many households on one deploy** via `household_id` isolation (MH-001–MH-007 **Verified** in [multi-household-change-checklist.md](multi-household-change-checklist.md)). Inside each household: Primary + optional Family Members, per-household Labels/Payment Methods/Ollama prefs, and household-scoped panel data. Evolution/WhatsApp per-household isolation (**MH-008**) and household-scoped backup ZIPs (**MH-009**) remain in progress.

**Future (this PRD):** public **Free / Pro** signups, billing, and quotas. That revenue layer is **not** authorized yet. The tenancy **kernel** is authorized only through the phased `MH-*` register — implement the active row only.

---

## 2. Terms

| Term | Meaning |
|------|---------|
| **Multi-user (inside a household)** | Primary and login-enabled Family Members share one books today. Keep this. See [household-access.md](household-access.md). |
| **Isolation between signups** | Unrelated people who Register must not see each other’s data. That is the SaaS boundary. |
| **Household / account** | The isolation unit: the registering Primary’s books, plus invited family logins **inside** that unit. |
| **Logged-in user** | Who is using the panel (`users.id`). Not the isolation key. |

Avoid calling per-login `user_id` scoping “multi-user instead of multi-tenant.” Isolation between strangers still needs a **household/account** grouping column (name TBD in implementation: e.g. `household_id` or `owner_user_id`). The PRD specifies **behavior**, not the column name.

---

## 3. FAQ — Why not only `user_id`?

Expenses today are attributed with `family_member_id` (`null` = Primary), not `users.id`. Family logins **list household rows** and only **mutate** their own. Labels, payment methods, Evolution, Ollama settings, and backups are household-wide.

If every query were `where user_id = auth()->id()`:

- A spouse with login enabled would **not** see the Primary’s expenses (breaks the current household product).
- WhatsApp receipts attributed via `family_member_id` would not match the family user’s `user_id` without extra mapping.
- Unrelated signup C would still collide on singleton Ollama / Evolution unless those are scoped by a **household/account** key.

**Logged-in user ≠ isolation key.** Isolation key = the account/household (the registering Primary’s books). Family members are extra logins **inside** that key. Avoiding the word “tenant” does not avoid a grouping column.

---

## 4. Current vs target

| Concern | Today (live) | SaaS target (future) |
|---------|--------------|----------------------|
| Deploy | Many households per deploy (`household_id`) | Same + public Register UI + billing |
| Expenses / budgets / recurrings | Per household; family ACL inside | Same; no cross-household reads |
| Labels / payment methods | Per household | Same |
| `OllamaSetting` | Per household row (shared **process/GPU** OK) | Same + per-household quotas |
| Evolution API | Per-household settings row; HH#1 env fallback; HH#2 E2E in MH-008 | Fully isolated instances + secrets per household |
| WhatsApp allowlist | Per household | Same |
| Backups / Danger Zone | Catalog + wipe scoped; ZIP still full-DB (MH-009) | Per-household ZIP create/restore |
| Registration | `HouseholdRegistrationService` (no public Filament Register yet) | Public Register → **new** household + Primary |
| Plans | None | Free / Pro on the **household** (matrix TBD) |

---

## 5. Actors

| Actor | Behavior |
|-------|----------|
| **Stranger** | Register → creates a **new household** and becomes its Primary. Never joins an existing household by accident. |
| **Invited family** | Family Member on **that** household: shared lists, own mutate ACL, same product rules as today. Not a second tenant. |
| **Operator (you)** | Primary of household #1 on a private deploy; pre-migration data backfilled to household #1 (MH-004). |

---

## 6. Isolation (non-negotiable)

**Between households:** expenses, labels, budgets, recurrings, backups, Ollama settings, Evolution credentials / instance / webhook, WhatsApp allowlist, and plan entitlements.

**Inside a household:** keep current family ACL ([household-access.md](household-access.md)) — list household data; mutate assigned records; Primary owns Integrations / Settings / Tools.

**Shared across the platform (allowed):** Ollama **process / GPU** as a platform parsing service (with per-household quotas later).

**Not shared:** one WhatsApp bot / Evolution instance for all customers. Each Pro household that uses WhatsApp needs its own linked session and webhook credentials.

---

## 7. Plans — Free / Pro (recommended default, TBD)

Every row is **TBD** until product lock. Recommended default for discussion only:

| Capability | Free (recommended) | Pro (recommended) | Status |
|------------|--------------------|-------------------|--------|
| Register + own household | Yes | Yes | TBD |
| Finances UI (upload, expenses, budgets, labels) | Yes | Yes | TBD |
| Household Family Members (panel invite) | Yes | Yes | TBD |
| Ollama / AI parsing | Yes, within quota | Higher quota | TBD |
| WhatsApp / Evolution (own instance + webhook) | No | Yes | TBD |
| Family WhatsApp OTP login | No / limited | Yes | TBD |
| Upload / parse limits | Lower | Higher | TBD |
| Training / Health / Task modules | Not plan differentiators yet | Same | TBD |

Defer: billing vendor, price, currency, trial length, and upgrade UX. Plans attach to the **household**, not to each family login.

---

## 8. Phased delivery (doc-only roadmap)

Implementation order is the `MH-*` register — not this section. Status summary:

1. **Kernel quality** — Ongoing via [security-audit.md](security-audit.md); Evolution / Ollama setup.
2. **Household / account scope** — **Verified** (MH-004–MH-006): `household_id`, scopes, isolation tests.
3. **Register** — **Verified** at service layer (MH-007): `HouseholdRegistrationService`; public Filament Register deferred.
4. **Evolution / WhatsApp** — **In progress** (MH-008): per-household instances and HH#2 E2E.
5. **Backups** — **Implemented** partial (MH-009): catalog + wipe scoped; ZIP still full-DB.
6. **Plans / billing** — **Deferred** until an explicit later phase.

**Not authorized yet:**

- Filament `->registration()` without an explicit follow-up request
- Scoping only by `auth()->id()`
- Per-login Evolution API keys
- Plan feature checks without an account/household column

Optional revenue **without** tenancy: separate installs per customer (own DB / `.env` / Evolution). That stays aligned with today’s architecture.

---

## 9. Security / go-live

SaaS increases blast radius (many households, public Register, webhooks per tenant). This PRD is **not** permission to skip security work.

Before a public SaaS deploy:

- Critical and High items in [security-audit.md](security-audit.md) must be Verified (or explicitly accepted).
- Follow the public-release gate in [security-hardening-playbook.md](security-hardening-playbook.md).
- Keep Ollama and Evolution off the public internet where possible; expose only the app (and tenant webhook URLs) over HTTPS.

---

## 10. Out of scope until later phases

This document does **not** authorize:

- Stripe or any billing integration / Free/Pro entitlements
- Skipping the `MH-*` order in [multi-household-change-checklist.md](multi-household-change-checklist.md)
- New top-level `app/` folders without approval
- Spatie multi-tenancy or permissions packages

Schema, Register, Evolution tenancy, and household-scoped backups are authorized only through the matching **Verified** `MH-*` prerequisites in the checklist (architecture unlock is **MH-002**).

When implementing: follow [system-architecture.md](system-architecture.md) and the active `MH-*` row only.

---

## Related docs

| Doc | Role |
|-----|------|
| [system-architecture.md](system-architecture.md) | **Live** product blueprint (tenancy phase authorized; implement via MH-*) |
| [multi-household-change-checklist.md](multi-household-change-checklist.md) | Phased `MH-*` implementation register |
| [household-access.md](household-access.md) | Family ACL inside a household |
| [security-audit.md](security-audit.md) | Pre-public security register |
| [security-hardening-playbook.md](security-hardening-playbook.md) | How to close SEC-* items |
| [agent-onboarding.md](agent-onboarding.md) | Agent read order |
