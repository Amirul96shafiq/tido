# Documentation Index

| Doc | Audience | Purpose |
|-----|----------|---------|
| [../README.md](../README.md) | Humans (GitHub) | Product overview, install, usage, license |
| [agent-onboarding.md](agent-onboarding.md) | Cursor / AI agents | How the app works and how to code for it |
| [system-architecture.md](system-architecture.md) | Agents + humans | Product blueprint; do not contradict without warning |
| [ollama-setup.md](ollama-setup.md) | Ops | Native host Ollama / qwen2.5vl:7b (no Docker) |
| [evolution-local-windows.md](evolution-local-windows.md) | Ops | WhatsApp Evolution instance + webhook (Windows host) |
| [google-drive-setup.md](google-drive-setup.md) | Ops | Drive folder sync credentials |
| [ui-empty-states.md](ui-empty-states.md) | Agents + humans | Illustrated empty panels (email-change expiry pattern) |
| [ui-modal-overlay.md](ui-modal-overlay.md) | Agents + humans | Modal backdrop blur + Filament action modal width |
| [ui-tooltips.md](ui-tooltips.md) | Agents + humans | Filament Tippy tooltips on icon CTAs (not browser `title`) |
| [ui-dark-theme.md](ui-dark-theme.md) | Agents + humans | Dark mode Slate surfaces, tooltips, scrollbars, solid CTA text |
| [ui-copy-style.md](ui-copy-style.md) | Agents + humans | Impersonal UI voice (no we/you); auth and panel copy |
| [content-draft-recovery.md](content-draft-recovery.md) | Agents + humans | Auto-save drafts + crash recovery on Filament Create/Edit |
| [backups-and-danger-zone.md](backups-and-danger-zone.md) | Agents + humans | Backup catalog, restore tokens, guest restore, profile Danger Zone |
| [git-workflow.md](git-workflow.md) | Agents + humans | Feature/fix branches → PR → main; staging/production promotion |

## Cursor agent assets (outside `docs/`)

| Path | Purpose |
|------|---------|
| `AGENTS.md` | Boost guidelines + tido entry pointer |
| `.cursorrules` | Hard coding / security directives |
| `.cursor/rules/*.mdc` | Scoped always-on / glob rules |
| `.cursor/skills/tido-domain/` | Domain skill (mirrored in `.agents/skills/`) |
| `.cursor/skills/laravel-best-practices/` | Laravel patterns |
| `.cursor/skills/pest-testing/` | Pest conventions |
| `.cursor/skills/configuring-horizon/` | Horizon |
| `.cursor/skills/tailwindcss-development/` | Tailwind v4 |
