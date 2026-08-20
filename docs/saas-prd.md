# SaaS PRD — Future Product (Companion)

> **Status:** Draft / future-state  
> **Do not implement from this file** while [system-architecture.md](system-architecture.md) defines a **single-tenant** personal hub.  
> Tenancy, public registration, Free/Pro billing, and schema changes require an explicit later phase that **updates the architecture document** first. This PRD is product intent only.

Product name remains **tido**. Expense tags remain **Label** / **Labels** (never Category).

---

## 1. Problem

Today tido is a **single-tenant personal hub**: one install, one household (Primary + optional Family Members), one Evolution connection, one Ollama settings row. That model fits a private deploy for one family.

Revenue via public **Free / Pro** signups needs **many households on one deploy** without mixing books, WhatsApp sessions, or settings. This document describes that future product. It does **not** authorize building it yet.

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
| Deploy | One household per install | Many households per deploy |
| Expenses / budgets / recurrings | One household books; family ACL inside | Same ACL **inside** each household; no cross-household reads |
| Labels / payment methods | Shared for the install | Per household |
| `OllamaSetting` | Singleton (`id = 1`) | Per household (shared **process/GPU** OK) |
| Evolution API | One URL / key / webhook secret / instance | Per household instance + secrets |
| WhatsApp allowlist | Profile + Family Members for that install | Per household |
| Backups / Danger Zone | Household operator | Per household |
| Registration | None (login + invite only) | Register → **new** household + Primary |
| Plans | None | Free / Pro on the **household** (matrix TBD) |

---

## 5. Actors

| Actor | Behavior |
|-------|----------|
| **Stranger** | Register → creates a **new household** and becomes its Primary. Never joins an existing household by accident. |
| **Invited family** | Family Member on **that** household: shared lists, own mutate ACL, same product rules as today. Not a second tenant. |
| **Operator (you)** | First household on a private deploy until SaaS ships; still one household until the tenancy phase lands. |

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

Order matters. Do **not** skip ahead.

1. **Kernel quality** — Finish single-tenant security and integration work ([security-audit.md](security-audit.md), Evolution / Ollama setup). The SaaS kernel is a copy of this household product.
2. **Household / account scope** — Add the grouping key and global scopes with **one** live household in production. Prove isolation in tests before Register.
3. **Register** — Signup creates a new household + Primary (not a second user on household 1).
4. **Plans / billing** — Free/Pro flags and payment after isolation is proven.

**Not first:**

- Filament `->registration()` on the current single-tenant panel
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

## 10. Out of scope for this PRD

This document does **not** authorize:

- Migrations or `household_id` / `owner_user_id` columns
- Stripe or any billing integration
- Public Register UI
- Changes to Evolution / Ollama pages for multi-account
- New top-level `app/` folders
- Rewriting [system-architecture.md](system-architecture.md) as SaaS (that is a later, explicit phase)

When the tenancy phase starts: update [system-architecture.md](system-architecture.md) first, then implement against that contract.

---

## Related docs

| Doc | Role |
|-----|------|
| [system-architecture.md](system-architecture.md) | **Live** product blueprint (single-tenant) |
| [household-access.md](household-access.md) | Family ACL inside a household |
| [security-audit.md](security-audit.md) | Pre-public security register |
| [security-hardening-playbook.md](security-hardening-playbook.md) | How to close SEC-* items |
| [agent-onboarding.md](agent-onboarding.md) | Agent read order |
