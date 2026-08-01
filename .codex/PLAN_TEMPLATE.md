# Plan: <short task title>

- **Status:** Draft
- **Mode:** Plan
- **Created:** YYYY-MM-DD
- **Updated:** YYYY-MM-DD
- **Requested by:** User
- **Current branch:** <branch or not-yet-created>
- **Implementation branch:** <feature/slug or fix/slug>

## Request

State the requested outcome in concrete terms.

## Decisions and questions

Record user answers, repository-derived decisions, unresolved blockers, and the consequences of each material choice.

## Current-state evidence

List the inspected entry points, execution path, persisted state, sibling patterns, relevant tests, documentation, installed-version API evidence, and any known failures. Link to repository files where helpful.

## Scope

### In scope

- <coherent change>

### Out of scope

- <explicit non-goal>

## Acceptance criteria

- [ ] <observable behavior or artifact>
- [ ] <error, authorization, or edge behavior>
- [ ] <verification evidence required before handoff>

## Impact map

| Layer | Expected impact | Files or symbols |
|---|---|---|
| Architecture | None / describe | |
| Database | None / describe | |
| Backend | None / describe | |
| Queues/integrations | None / describe | |
| Filament/UI | None / describe | |
| Security/privacy | None / describe | |
| Documentation | None / describe | |

## Implementation sequence

1. <small, dependency-ordered step>
2. <next step>
3. <cleanup or documentation step>

## Verification plan

Select the exact checks from [`VERIFICATION.md`](VERIFICATION.md).

- **Targeted tests:** `<command or not applicable>`
- **Formatting/static analysis:** `<command or not applicable>`
- **Frontend build/browser:** `<command and flow or not applicable>`
- **Integration/async completion:** `<entry point, queue, final persisted state or not applicable>`
- **Documentation/config validation:** `<checks or not applicable>`
- **Full pre-PR suite:** `php artisan test --compact` / explicitly deferred

## Risks and approval gates

- <dependency, migration, destructive, external, production, Git publication, or other gate>

## Rollback or recovery

Describe how to reverse the change safely, or state why no special rollback is needed.

## Handoff

- **Ready when:** all blocking questions are resolved and every implementation step has a matching verification step.
- **Next action:** wait for the user to say `proceed`, `implement`, or switch to Agent.
