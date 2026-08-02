# Git workflow — feature branches & collaboration

How tido uses Git so solo work, Cursor agents, and future multi-developer collaboration stay consistent.

## Why

- Keep `main` stable and reviewable
- Isolate unfinished work per change
- Make review, rollback, and parallel work straightforward
- Scale to staging/production promotion when those environments exist

## Branch roles

| Branch | Role | Lifetime |
|--------|------|----------|
| `feature/*` / `fix/*` | Where changes are made | Short — delete after merge |
| `main` | Shared integration; PR target | Permanent |
| `staging` | Deploy target for the staging server | Permanent (promotion only) |
| `production` | Deploy target for live | Permanent (promotion only) |

Developers do **not** code day-to-day on `staging` or `production`. Coding stays on short-lived feature/fix branches that merge into `main` via PR.

Until staging and production servers exist, use only `main` + feature/fix branches. Create `staging` / `production` from `main` when those environments go live.

```
feature/fix  →  PR  →  main  →  staging  →  production
                 (review)     (promote)   (promote)
```

## Daily loop

1. Update `main`:

```bash
git checkout main
git pull
```

2. Create a branch from `main`:

```bash
git checkout -b feature/short-kebab-name
# or
git checkout -b fix/short-kebab-name
```

3. Commit only that concern on the branch. Keep the branch short-lived.

4. Push and open a **PR into `main`**:

```bash
git push -u origin HEAD
```

5. After the PR is merged and checks are OK:

```bash
git checkout main
git pull
git branch -d feature/short-kebab-name
```

6. Start the next change with a **new** branch from latest `main` — even if it touches the same feature area.

Never push unfinished work straight to `main`, `staging`, or `production`.

## Pull request body standard

Use the established format from recent merged pull requests, especially [PR #60](https://github.com/Amirul96shafiq/tido/pull/60):

```markdown
## Summary

- Concise bullet describing the first cohesive change.
- Concise bullet describing the user-visible or developer-facing result.
- Concise bullet describing relevant tests or documentation when useful.

## Test plan

- [ ] Run `php artisan migrate` and confirm the expected schema or configuration exists.
- [ ] Open the affected page, perform the user action, and confirm the expected success state or notification.
- [ ] Exercise each relevant role, permission, empty state, error state, or integration path and confirm the expected result.
- [ ] Confirm each relevant link, redirect, notification, relationship label, status, or persisted value.
- [ ] Run: `php artisan test --compact tests/Feature/RelevantTest.php tests/Unit/RelevantTest.php`
```

Rules:

- Keep `## Summary` first and use concise bullets in the imperative or present tense.
- Use `## Test plan` as step-by-step instructions for a reviewer to execute before approving the pull request, not as a retrospective list of checks already run by Codex.
- Write manual checks as concrete actions followed by the expected result. Include setup, migrations, page navigation, role/permission checks, notifications, integrations, and failure paths when they are relevant.
- Put automated checks at the end using `Run:` followed by the exact command. Include pass/assertion counts in a separate validation note only when the command has already been run.
- Prefix every test-plan step with an unchecked Markdown box (`- [ ]`), including manual checks and `Run:` commands. Leave every box unchecked so the reviewer can complete it during review.
- Do not use checked (`[x]`) boxes or plain unmarked bullets in the generated test plan.
- Add `## Full-suite note` when the full suite was run with unrelated failures or when a broader validation caveat must be preserved.
- Add `## Reviewer note` only for a concrete reviewer action, security concern, known risk, or follow-up decision.
- Use `## Target` only when the target branch needs explicit clarification; normal pull requests target `main` and do not need a redundant section.
- Do not use generic `## What changed`, `## Why`, `## User impact`, `## Developer impact`, `## Root cause`, or `## Verification` headings as the default template. Put that information into the Summary bullets or an applicable note section while preserving the established Summary/Test plan structure.
- For documentation-only changes, give step-by-step link, anchor, terminology, mirror, or instruction checks and finish with `Run: git diff --check`.

## Naming

| Prefix | Use |
|--------|-----|
| `feature/<short-kebab>` | New behaviour or enhancement |
| `fix/<short-kebab>` | Bugfix |

Examples: `feature/content-draft-recovery`, `fix/draft-recovery-poll-interval`.

One coherent change set per branch. Follow-ups after merge get a new branch from `main`, not a long-lived feature branch.

## Commit Message Format

When auto-generating or writing commit messages, use this recommended baseline format:

```text
<type>: <concise lowercase summary>

<sentence 1 describing the primary change>
<sentence 2 describing the important implementation or behavior change>
<sentence 3 describing tests, verification, or other meaningful affected work>
```

- **Type**: `refactor`, `fix`, `feat`, `docs`, or `chore`.
- **Summary**: A concise lowercase description after the type and colon, with one space after the colon and no trailing period.
- **Body**: Use three concise plain-text sentences by default, and add a fourth, fifth, or further sentence when the total changes in the commit require additional context. Separate the body from the subject by one blank line and write each sentence on its own line without bullets, numbering, headings, or a code fence.

## Multi-developer rules

- All PRs target `main` (the only integration branch)
- Pull `main` before creating a branch; if `main` moves while you work, merge or rebase `main` into your feature branch
- Do not force-push shared branches: `main`, `staging`, `production`
- Prefer small PRs; one feature or fix per branch
- When collaborators join: protect `main` on GitHub (require PR; optional required CI)

## Staging / production promotion

Documented now; create the branches only when the servers exist.

1. Create `staging` and `production` from `main` when those environments are ready
2. Promote when ready to test: merge (or PR) `main` → `staging`
3. Promote when ready to ship: merge (or PR) `staging` → `production`

Hotfix path (preferred):

1. Branch `fix/...` from latest `main`
2. Merge to `main` via PR
3. Promote through `staging` → `production`

Avoid maintaining the same fix on two long-lived branches unless it is a true emergency.

There is no long-lived `develop` branch. Full Git Flow (`develop` / `release/*`) is out of scope unless adopted later.

## Checklist

- [ ] On latest `main` before branching
- [ ] Branch named `feature/...` or `fix/...`
- [ ] One concern on the branch
- [ ] Tests pass on the branch
- [ ] PR opened into `main`
- [ ] After merge: back on `main`, local feature branch deleted
- [ ] Next change starts a new branch from `main`

## What not to do

- Code features directly on `main`
- Use one long-lived branch for unrelated work
- Branch a new feature off an unfinished feature branch
- Leave merged feature branches hanging forever
- Commit day-to-day work on `staging` or `production`
- Force-push `main`, `staging`, or `production`
