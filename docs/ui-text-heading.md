# UI text headings (Title Case)

**Always write user-visible headings in Title Case:** capitalize the first letter of every word.

| Correct | Incorrect |
|---------|-----------|
| **Text Heading** | Text heading |
| **Text Heading** | text heading |
| **Pipeline Readiness** | Pipeline readiness |
| **Receipt & Parsing Activity** | Receipt & parsing activity |
| **Swap Account** | Swap account |
| **Restore Backup** | Restore backup |
| **Service Status** | Service status |

Sentence case and all-lowercase headings are not allowed.

Voice still follows [ui-copy-style.md](ui-copy-style.md) (impersonal; no *we* / *you*). This doc only governs **capitalization**.

## Applies to

Any short title shown as a heading, not body copy:

- Filament page titles (`getHeading()`, `$title`, `$navigationLabel`)
- `Section::make('…')` and `<x-slot name="heading">`
- Section-nav tab labels (`sectionNavItems()`)
- Empty-state headings (`emptyStateHeading()`, `<x-empty-state-panel heading="…">`)
- Modal headings (`fi-modal-heading`, action `->modalHeading()`)
- Widget headings (`<x-slot name="heading">`)
- Table column labels and filter headings when they read as titles
- Global-search destination titles for pages and sections
- Auth page headings (`getHeading()`)

Descriptions, helper text, notifications, validation messages, and button labels are **not** headings. Keep those in sentence case unless they are already an imperative CTA (*Send Email* is a button, not a heading).

## Exceptions

| Keep as-is | Why |
|------------|-----|
| **tido** | Product name stays lowercase in prose and in headings |
| **MYR**, **API**, **OCR**, **PDF**, **OTP**, **URL** | Established acronyms stay uppercase |
| Record names / merchant strings | Do not Title-Case live data (`Starbucks`, `qwen2.5vl:7b`) |
| Login brand headline | Conversational product voice on the login split (`Keep it tidy. Get it done.`) — see [ui-copy-style.md](ui-copy-style.md) |

Hyphens: capitalize each segment (`Active Sessions`, `WhatsApp Official API`).

The separator `·` on Edit titles is unchanged (`Edit Overall Budget · Monthly 2026 Budget`). Apply Title Case to each phrase around it.

## Agent checklist

1. New or edited heading strings: every word starts with a capital letter (`Text Heading`).
2. Do not “fix” headings into sentence case to match Filament stock English.
3. Anchors stay kebab-case (`->id('pipeline-readiness')`); only the **visible** label is Title Case.
4. Update Pest `assertSee` / section-nav tests when a heading string changes.
5. Match [ui-copy-style.md](ui-copy-style.md) for voice; this file for capitalization.

## Related

- [ui-copy-style.md](ui-copy-style.md) — impersonal voice
- [ui-section-nav.md](ui-section-nav.md) — tab labels
- [ui-empty-states.md](ui-empty-states.md) — empty panel headings
- [agent-onboarding.md](agent-onboarding.md) — Filament UI map
