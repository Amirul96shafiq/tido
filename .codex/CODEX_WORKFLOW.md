# Codex operating workflow for tido

This is Codex's canonical working procedure for this repository. Root [`AGENTS.md`](../AGENTS.md) is the always-loaded contract; this file supplies the detailed lifecycle. Cursor and Antigravity maintain separate instructions and are outside this workflow's scope.

## 1. Resolve the mode

Honor an explicit `Ask`, `Plan`, `Agent`, or `Debug` selection. When no mode is named, infer it from the requested outcome:

| User intent | Mode | Mutation boundary |
|---|---|---|
| Ask, explain, review, compare, summarize, or report | Ask | No repository or external mutation |
| Plan, design, explore options, or prepare work for later | Plan | Only one task-local `.codex/plans/*.md` file |
| Build, implement, change, refactor, fix, or execute | Agent | Requested repository changes and proportionate verification |
| Diagnose, troubleshoot, reproduce, trace, or debug | Debug | Evidence gathering and minimal temporary diagnostics; permanent fix needs authorization |

Use the least-mutating mode when intent is genuinely ambiguous. Ask one concise mode question only when the alternatives would materially change what Codex is allowed to do.

The mode continues through follow-ups until the user explicitly changes it or gives a clear action instruction. `Proceed`, `implement the plan`, and `fix it` select Agent. `Debug and fix` authorizes the fix after the fault is evidenced. Do not treat agreement with an explanation as implementation permission.

## 2. Shared preflight

The full preflight governs Plan, Agent, and Debug. Ask mode uses only the read-only inspection needed to answer accurately; it does not inspect branches, diffs, acceptance criteria, or verification plans unless the question concerns those things.

Scale the depth to the request, but do not skip relevant checks:

1. Restate the objective, acceptance criteria, non-goals, and current mode.
2. Inspect `git status --short --branch --untracked-files=all`, the relevant diff, intended untracked files, and user-owned changes. Never overwrite, revert, format, stage, or move unrelated work.
3. Establish the correct branch and base. Do not edit tracked product, test, configuration, instruction, or shared documentation files on `main`. A Git-ignored `.codex/plans/*.md` Plan artifact is allowed on any branch.
4. Read [`../docs/agent-onboarding.md`](../docs/agent-onboarding.md), then only the task-relevant documents and Codex-surfaced skills.
5. Trace the real implementation path, callers, persisted state, side effects, related tests, and sibling conventions before designing a change.
6. Search installed-version Laravel, Filament, Livewire, Horizon, and Pest documentation through Laravel Boost before code changes. Inspect the live schema through Boost before designing a migration. If Boost is unavailable, say so and use official documentation or installed vendor source.
7. Identify architecture, data, dependency, security, privacy, integration, deployment, destructive-operation, and external-state risks.
8. Select verification from [`VERIFICATION.md`](VERIFICATION.md) before editing, not after implementation.

Codex loads project-scoped `.codex/config.toml` only for trusted repositories and at task startup. After that configuration changes, start a new task or restart Codex before treating a newly configured MCP server as unavailable.

For complex tasks, maintain the active plan with Codex's planning tool in addition to any saved Plan-mode document. Keep at most one step in progress.

## 3. Ask mode

Ask mode is read-only and conversational.

Allowed:

- Read and search source, documentation, diffs, logs already present, configuration, and read-only database state when evidence is needed.
- Run read-only Git inspection such as status, diff, log, show, and branch listing.
- Explain options, risks, likely files, and recommended next actions.

Not allowed:

- File edits, generated artifacts, plan documents, branch creation, Git writes, tests, builds, formatters, migrations, application commands, service starts/stops, browser interaction that changes state, or external writes.
- Turning a review or explanation into a fix without an explicit switch to Agent.

End with the answer. Ask a question only when the user requested one or when an answer cannot be accurate without it.

## 4. Plan mode

Plan mode investigates and prepares work for a later Agent run. It does not implement.

1. Inspect the repository read-only and identify the actual affected paths.
2. Ask focused questions as decisions arise; do not ask what can be discovered safely from the repository.
3. Create one plan from [`PLAN_TEMPLATE.md`](PLAN_TEMPLATE.md) at `.codex/plans/YYYY-MM-DD-short-kebab-title.md`. Selecting Plan is explicit authorization for this ignored task-local file and satisfies the general documentation-creation gate.
4. Update the same plan as answers arrive. Do not create competing plan files for one task.
5. Mark the plan `Ready` only when acceptance criteria, scope, implementation order, verification, risks, and approval gates are actionable.
6. Return the plan file path, summarize material decisions and open blockers, and wait for the user to say `proceed`, `implement`, or switch to Agent.

The plan document is the only repository file Plan mode may create or edit. Plan files are intentionally Git-ignored and must not be staged or committed. Plan mode does not create branches, change code/configuration/shared docs, run tests or builds, start services, migrate data, or perform external writes.

## 5. Agent mode

Agent mode owns implementation through verified handoff.

1. Complete the shared preflight and communicate a concise implementation and verification plan.
2. If a Ready plan exists, re-read it and verify its assumptions against the current branch before editing. Update stale assumptions in the plan.
3. If the current branch is dirty for an unrelated concern, stop and ask how to isolate the work. Do not carry those changes onto a new branch. If currently on a safe `main`, create a short-lived `feature/<slug>` or `fix/<slug>` branch. Never hide or discard user changes to make branch switching work.
4. Make the smallest coherent change. Reuse sibling patterns and existing components. Do not opportunistically refactor unrelated code.
5. Keep Filament resources/controllers thin; put domain side effects in the existing Services, Jobs, Observers, Policies, or Support structure as appropriate.
6. Add or update tests for executable behavior. Do not weaken, delete, or skip a failing test to obtain a pass.
7. Run targeted checks during implementation, then the wider checks selected before editing.
8. Review architecture, security, documentation, migration/rollback, async completion, and user-visible behavior before declaring completion.
9. Review the complete diff and status. Distinguish regressions caused by the change from pre-existing failures.
10. Hand off with evidence. Do not commit, push, or open a PR until the user separately approves that Git action.

Agent mode should not stop for routine, reversible, in-scope implementation choices. Stop and ask when a missing choice materially changes behavior or when new authority is needed for dependencies, destructive operations, production/live external actions, secrets, architectural contradictions, or publication.

## 6. Debug mode

Debug mode is evidence-first and staged.

### Stage A — isolate

1. Define the observed symptom, expected behavior, reproduction conditions, last known good state, and affected environment.
2. Trace the execution path and inspect recent relevant logs, configuration, queue state, persisted state, and existing tests.
3. Reproduce locally when safe. Change one diagnostic variable at a time.
4. If evidence is insufficient, move to a safe `fix/*` branch when necessary, then add the smallest temporary instrumentation needed to distinguish hypotheses. Use an identifiable `Codex debug` marker and record every temporary file/line changed.
5. Validate temporary executable instrumentation with syntax checks, the closest existing targeted test when available, and a diff/privacy review. A new regression test is required for the permanent fix, not for temporary logging alone.
6. Never log tokens, credentials, session identifiers, raw receipt content, personal data, full webhook payloads, or other secrets. Redact identifiers that are not required for correlation.

### Stage B — user reproduction pause

When the failure depends on the user's browser, device, account, WhatsApp interaction, local service, or timing:

1. Stop after instrumentation is ready.
2. Give exact numbered reproduction steps.
3. State what output or observable result is needed and where it will appear.
4. Ask the user to perform the test and return the result.
5. Do not apply a speculative permanent fix while waiting.

This is an interim Debug handoff. List every temporary diagnostic location, confirm its privacy review and interim validation, and state how it will be removed. The terminal completion rule requiring diagnostics to be absent does not apply until diagnosis/fix work concludes.

If Codex can reproduce and gather equivalent evidence locally, continue without manufacturing a user pause.

### Stage C — analyze and fix

1. Compare the evidence against each hypothesis and identify the supported root cause.
2. Explain the cause and the minimal fix. If the request was Debug only, wait for `fix it` or Agent mode before changing permanent behavior.
3. If the original request explicitly authorized `debug and fix`, implement once evidence is sufficient.
4. Remove all temporary instrumentation unless the user explicitly approves keeping production-safe observability.
5. Add a regression test, run the relevant verification matrix, and reproduce the original flow again.

For receipt ingestion, queue, Ollama, Evolution, Drive, or Poppler work, success requires the asynchronous job to finish and the resulting invoice state/data to be inspected. Upload acceptance, a stored file, a queued job, or a webhook `2xx` is not completion.

Do not repeat an identical failing diagnostic more than twice without changing the hypothesis, evidence source, or method. Never broadly terminate every `php.exe`; identify the exact tido-owned PID and command line first.

## 7. Authorization boundaries

Codex may perform these actions in Agent mode, and the corresponding scoped diagnostic actions in Debug mode, without an additional approval when they are clearly inside the user's request:

- Read-only inspection and local diagnostics.
- Create or switch to a safe `feature/*` or `fix/*` branch.
- Edit files in the agreed scope.
- Generate standard Laravel classes with `--no-interaction`.
- Run targeted tests, Pint, static analysis, frontend builds, and local browser verification.
- Use read-only Boost documentation, schema, database, URL, and log tools.
- Start a scoped, non-external tido development process needed for reproduction, such as an exact queue worker, scheduler, Laravel server, or Vite process. Record the command and PID; stop only a process Codex started, by exact PID, before terminal handoff. Do not stop a pre-existing user process without approval.

Stop for explicit approval before:

- Adding, removing, or upgrading dependencies.
- Introducing a new top-level `app/` directory or contradicting the documented architecture.
- Destructive filesystem, database, Git, backup/restore, account, or process actions whose exact targets are not already authorized.
- Sending live messages, invoking production APIs, modifying remote systems, deploying, or touching production data.
- Starting Evolution/WhatsApp, Drive synchronization, or another process that connects to a live external account unless the user authorized that live interaction.
- Committing, amending, staging for the user, pushing, force-pushing, creating or merging a PR, or changing protected branches.

## 8. Git and branch discipline

- Start features and fixes from the appropriate `main` base according to [`../docs/git-workflow.md`](../docs/git-workflow.md).
- Branch creation is local and reversible; network updates to `main` can change state and should be surfaced when needed.
- Keep one coherent concern per branch. Do not attach unrelated foundation work to an active feature branch.
- Never use destructive Git recovery (`reset --hard`, forced checkout, cleaning untracked files) to work around a dirty tree.
- Read-only Git commands are always acceptable in Ask, Plan, Agent, and Debug modes.
- A commit, push, PR, merge, or deployment is a separate user-approved action, never an implied final step.

## 9. Completion contract

Completion means the requested outcome is demonstrated at the appropriate layer.

This terminal completion contract applies to completed Agent work and completed Debug diagnosis/fix work. Ask mode ends with an evidence-backed answer and does not run diff/status checks unless repository state is part of the question. Plan mode ends with a Ready plan and its documented blockers. An interim Debug reproduction handoff follows the Stage B exception above.

Before a terminal implementation/debug final response:

1. Re-read the user request and acceptance criteria.
2. Review `git diff --check`, the complete tracked diff, `git status --short --branch --untracked-files=all`, and the full content of every intended untracked file. Do not stage files merely to make them appear in `git diff`.
3. Run all selected checks from [`VERIFICATION.md`](VERIFICATION.md).
4. Confirm temporary diagnostics, generated debug data, and unrelated artifacts are absent.
5. Confirm documentation and intentional mirrors are synchronized when they were in scope.
6. Report the outcome first, then files changed, verification commands/results, limitations or skipped checks, remaining risks, and approval-dependent next steps.

Do not say “done,” “fixed,” or “working” when verification stopped at an intermediate state or when a required check was unavailable. State exactly what is proven and what remains unverified.
