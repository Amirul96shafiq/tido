# UI copy style

Preferred voice for user-facing text in tido — headings, descriptions, notifications, empty states, and auth pages.

## Voice

**Impersonal and neutral.** Describe what happened, what is required, or what the system does. Do not address the reader directly.

| Avoid          | Prefer                          |
| -------------- | ------------------------------- |
| we, we'll, our | _(omit or use passive)_         |
| you, your      | the, a, an, account, registered |
| Let's…         | _(omit)_                        |

### Examples (auth)

| Avoid                                               | Prefer                                                               |
| --------------------------------------------------- | -------------------------------------------------------------------- |
| Enter your email address and we'll send you a link… | Enter the registered email address to receive a password reset link. |
| Choose a new password for your account.             | Set a new password for the account.                                  |
| We sent a 6-digit code to +601…                     | 6-digit code sent to +601…                                           |
| You can request another code in                     | Another code available in                                            |
| Check WhatsApp for your 6-digit login code.         | Check WhatsApp for the 6-digit login code.                           |

Login brand copy (headline + tagline) may stay conversational when it is product voice, not instructional UI:

- **Sign In** heading: _Welcome Back!_ with an optional localized welcome loop (e.g. _Selamat kembali!_ for Malaysia) — see Sign In welcome greeting in [ui-reduce-motion.md](ui-reduce-motion.md)
- **Sign In** subheading: _Where tidy preparation meets finished work, then "tido" (sleep)._
- **Sign Up** form heading: _Hello!_ with an optional localized greeting loop (e.g. _Hai!_ for Malaysia) — see Sign Up greeting in [ui-reduce-motion.md](ui-reduce-motion.md)

Instructional text under headings (forms, OTP steps, password reset) must follow the impersonal rules above.

## Structure

| Element                 | Guidance                                                                                                                                                  |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Heading**             | Short label or statement; no second person; **Title Case every word** (`Text Heading`, not `Text heading`) — see [ui-text-heading.md](ui-text-heading.md) |
| **Description**         | One sentence; states purpose or next step without _you_ / _we_                                                                                            |
| **CTA / button**        | Imperative is fine when it names the action (_Send email_, _Verify code & sign in_) — not _Submit your form_                                              |
| **Notifications**       | Same neutral voice as descriptions                                                                                                                        |
| **Errors / validation** | Filament/Laravel defaults may use _you_; override in custom messages when touching that surface                                                           |

## Product naming

- Product: **tido** only (lowercase in prose unless start of sentence)
- Expense tags: **Label** / **Labels** in UI (not Category)

## Where copy lives

| Surface            | Location                                                                                           |
| ------------------ | -------------------------------------------------------------------------------------------------- |
| Auth pages         | `app/Filament/Pages/Auth/` — `getHeading()`, `getSubheading()`, action labels, notification bodies |
| Empty states       | Blade `heading` / `description` on `<x-empty-state-panel>`                                         |
| Filament resources | Form labels, helper text, notifications on actions                                                 |
| Emails / WhatsApp  | `app/Notifications/`, `app/Services/WhatsApp*` message builders                                    |

Stock Filament translation strings under `vendor/` are not the source of truth for tido voice. Override in custom page classes or app lang files when user-visible copy matters.

## Agent checklist

1. New or edited UI copy: scan for _we_, _you_, _your_, _our_, _let's_ — rewrite before shipping
2. Headings use Title Case (`Text Heading`) — see [ui-text-heading.md](ui-text-heading.md)
3. Descriptions sit under headings with tight spacing (see auth CSS in `resources/css/app.css` — `.fi-simple-header-subheading`)
4. Match tone: calm, specific, one clear next action (see [ui-empty-states.md](ui-empty-states.md) for layout)
5. Add or update Pest `assertSee` when copy is part of tested behaviour

## Related

- [ui-text-heading.md](ui-text-heading.md) — heading capitalization (Title Case)
- [agent-onboarding.md](agent-onboarding.md) — Filament UI section
- [ui-empty-states.md](ui-empty-states.md) — empty panel layout
- [ui-dark-theme.md](ui-dark-theme.md) — dark mode surfaces
