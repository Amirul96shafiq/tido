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

## Naming

| Prefix | Use |
|--------|-----|
| `feature/<short-kebab>` | New behaviour or enhancement |
| `fix/<short-kebab>` | Bugfix |

Examples: `feature/content-draft-recovery`, `fix/draft-recovery-poll-interval`.

One coherent change set per branch. Follow-ups after merge get a new branch from `main`, not a long-lived feature branch.

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
