# UI copy style

Preferred voice for user-facing text in tido — headings, descriptions, notifications, empty states, and auth pages.

## Voice

**Impersonal and neutral.** Describe what happened, what is required, or what the system does. Do not address the reader directly.

| Avoid | Prefer |
|-------|--------|
| we, we'll, our | *(omit or use passive)* |
| you, your | the, a, an, account, registered |
| Let's… | *(omit)* |

### Examples (auth)

| Avoid | Prefer |
|-------|--------|
| Enter your email address and we'll send you a link… | Enter the registered email address to receive a password reset link. |
| Choose a new password for your account. | Set a new password for the account. |
| We sent a 6-digit code to +601… | 6-digit code sent to +601… |
| You can request another code in | Another code available in |
| Check WhatsApp for your 6-digit login code. | Check WhatsApp for the 6-digit login code. |

Login brand copy (headline + tagline) may stay conversational when it is product voice, not instructional UI:

- Heading: *Keep it tidy. Get it done.*
- Subheading: *Where tidy preparation meets finished work, then "tido" (sleep).*

Instructional text under headings (forms, OTP steps, password reset) must follow the impersonal rules above.

## Structure

| Element | Guidance |
|---------|----------|
| **Heading** | Short label or statement; no second person |
| **Description** | One sentence; states purpose or next step without *you* / *we* |
| **CTA / button** | Imperative is fine when it names the action (*Send email*, *Verify code & sign in*) — not *Submit your form* |
| **Notifications** | Same neutral voice as descriptions |
| **Errors / validation** | Filament/Laravel defaults may use *you*; override in custom messages when touching that surface |

## Product naming

- Product: **tido** only (lowercase in prose unless start of sentence)
- Expense tags: **Label** / **Labels** in UI (not Category)

## Where copy lives

| Surface | Location |
|---------|----------|
| Auth pages | `app/Filament/Pages/Auth/` — `getHeading()`, `getSubheading()`, action labels, notification bodies |
| Empty states | Blade `heading` / `description` on `<x-empty-state-panel>` |
| Filament resources | Form labels, helper text, notifications on actions |
| Emails / WhatsApp | `app/Notifications/`, `app/Services/WhatsApp*` message builders |

Stock Filament translation strings under `vendor/` are not the source of truth for tido voice. Override in custom page classes or app lang files when user-visible copy matters.

## Agent checklist

1. New or edited UI copy: scan for *we*, *you*, *your*, *our*, *let's* — rewrite before shipping
2. Descriptions sit under headings with tight spacing (see auth CSS in `resources/css/app.css` — `.fi-simple-header-subheading`)
3. Match tone: calm, specific, one clear next action (see [ui-empty-states.md](ui-empty-states.md) for layout)
4. Add or update Pest `assertSee` when copy is part of tested behaviour

## Related

- [agent-onboarding.md](agent-onboarding.md) — Filament UI section
- [ui-empty-states.md](ui-empty-states.md) — empty panel layout
- [ui-dark-theme.md](ui-dark-theme.md) — dark mode surfaces
