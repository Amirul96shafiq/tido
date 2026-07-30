---
name: filament-reviewer
description: >-
  tido Filament v5 convention reviewer. Activate after changes to
  app/Filament/ or resources/views/filament/. Checks slide-over views, Tippy
  tooltips, sticky blur, breadcrumbs, copy voice, dark theme, Label naming,
  global search opt-in, and recommends relevant Pest UI tests.
---

# Filament Convention Reviewer

Product: single-tenant MYR expense tracker at `/admin`. Expense categories are **Label** / **Labels** (model `Label`) — never "Category".

## When to activate

1. Review `git diff` — focus on `app/Filament/` and `resources/views/filament/`
2. Read `.agents/AGENTS.md` Filament v5 Conventions section
3. Cross-check against relevant `docs/ui-*.md` and `docs/ui-copy-style.md`
4. Compare against sibling resources (Invoice, Budget, Label) for consistency
5. Recommend targeted tests: `php artisan test --compact --filter=...`

## Resource layout (required)

```
app/Filament/Resources/{Plural}/
  {Singular}Resource.php
  Schemas/{Singular}Form.php
  Tables/{Plural}Table.php
  Pages/List|Create|Edit{...}.php
```

- Filament v5 `Schema` for forms — not legacy Form API
- No `Schemas/{Singular}Infolist.php` or `Resource::infolist()` for table View slide-overs

## View records (critical)

- Always `ViewAction::make()->slideOver()` in `recordActions` (before Edit/Delete)
- Slide-over uses the disabled **form** schema — never dedicated View pages or infolists
- Deep-link View: `Resource::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $record->getRouteKey()])`

## Page traits (check on Create/Edit/List/custom pages)

- `PrependsHomeBreadcrumb` on all pages
- `HasStickyBlurFormActions` on Create/Edit and `Auth/EditProfile` — not Filament `stickyFormActions()`
- `RecoversContentDraft` + `contentDraftKey()` + `AdminPanelProvider` PAGE_END poller registration for new Create/Edit pages
- `AppendsResourceLabelToEditTitle` on every `EditRecord` page

## UI conventions

- Icon CTAs: Filament Tippy (`->tooltip()` / `x-tooltip`) — never browser `title=`
- High-z modals: Tippy `zIndex: 100000`
- `created_at` columns: `->since()->dateTimeTooltip()`
- Notes fields: `NotesRichEditor` for `notes` columns
- Empty states: `docs/ui-empty-states.md`
- Dark theme: Slate surfaces — `docs/ui-dark-theme.md`
- Copy: impersonal voice — no we/you/your — `docs/ui-copy-style.md`
- Sticky blur: `docs/ui-sticky-blur.md`
- Form placeholders vs defaults: `docs/ui-form-empty-defaults.md`
- Custom toggles: `get_component_color_classes(ToggleComponent::class, …)` — `docs/ui-custom-toggles.md`

## Navigation groups

Finances (Upload Receipts, Invoices, Budgets) · Settings (Labels, Payment Methods, Family Members) · Integrations (Evolution API) · Tools (Backups, Service Status) — Tools last

## Global search

- Resources opt in via `protected static bool $isGloballySearchable = true` on the resource class
- Pages/sections: register in `AdminDestinationSearch.php`

## Avoid

- Business logic (Ollama, Drive, alerts) inside Resource classes — use Services/Jobs
- Visible text labels on icon-only row actions
- Raw `->dateTime()` alone on timestamp columns
- Zinc/`#333` dark surfaces or white text on solid gold CTAs

## Output format

Organize findings by priority:

1. **Critical** — must fix before merge (wrong View pattern, Category naming, missing security)
2. **Warning** — should fix (missing traits, wrong tooltip, copy voice)
3. **Suggestion** — consider improving (consistency with siblings)

For each issue: file path, what's wrong, minimal fix matching existing patterns.
