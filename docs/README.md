# Documentation Index

| Doc | Audience | Purpose |
|-----|----------|---------|
| [../README.md](../README.md) | Humans (GitHub) | Product overview, install, usage, license |
| [agent-onboarding.md](agent-onboarding.md) | Cursor / AI agents | How the app works and how to code for it |
| [system-architecture.md](system-architecture.md) | Agents + humans | Product blueprint; do not contradict without warning |
| [security-audit.md](security-audit.md) | Agents + humans | Source-level security finding register, evidence, status, and public-release blockers |
| [security-hardening-playbook.md](security-hardening-playbook.md) | Agents + humans | One-finding-at-a-time AI implementation, verification, and handoff procedure |
| [dashboard-views.md](dashboard-views.md) | Agents + humans | Modular Home dashboard (Finances / Training / Health / Task) |
| [ollama-setup.md](ollama-setup.md) | Ops | Native host Ollama / qwen2.5vl:7b (no Docker) |
| [evolution-local-windows.md](evolution-local-windows.md) | Ops | WhatsApp Evolution instance, webhook, PDF media, and LID allowlist (Windows host) |
| [realtime-broadcasting.md](realtime-broadcasting.md) | Ops + agents | Reverb + Filament Echo for expense tables, Monthly Spending Overview, and database notifications (port 8081) |
| [whatsapp-manual-expense.md](whatsapp-manual-expense.md) | Humans + agents | Text-only WhatsApp manual expense format and pipeline |
| [whatsapp-bot-commands.md](whatsapp-bot-commands.md) | Humans + agents | WhatsApp media handling, command / keyword reference, and auto-replies |
| [ui-empty-states.md](ui-empty-states.md) | Agents + humans | Illustrated empty panels (email-change expiry pattern) |
| [ui-modal-overlay.md](ui-modal-overlay.md) | Agents + humans | Modal backdrop blur + Filament action modal width |
| [vite-assets.md](vite-assets.md) | Agents + humans | Vite panel assets: `Vite::asset()` vs `@vite`, when `npm run build` is required |
| [ui-sticky-blur.md](ui-sticky-blur.md) | Agents + humans | Sticky top/bottom bars with progressive blur veil |
| [ui-section-nav.md](ui-section-nav.md) | Agents + humans | Shared sticky section tabs + smooth scroll / hash deep links |
| [ui-tooltips.md](ui-tooltips.md) | Agents + humans | Filament Tippy tooltips on icon CTAs (not browser `title`) |
| [ui-text-marquee.md](ui-text-marquee.md) | Agents + humans | Looping single-line overflowing text (`x-tido.text-marquee`) |
| [ui-notes-rich-editor.md](ui-notes-rich-editor.md) | Agents + humans | Shared `NotesRichEditor` for `notes` fields (toolbar + height) |
| [ui-field-character-limits.md](ui-field-character-limits.md) | Agents + humans | Text field max length + live `current/max` counters |
| [ui-form-empty-defaults.md](ui-form-empty-defaults.md) | Agents + humans | Placeholders vs defaults on resource Create/Edit forms |
| [ui-custom-toggles.md](ui-custom-toggles.md) | Agents + humans | Custom Blade toggles: Filament color classes + inlineLabel layout |
| [ui-dark-theme.md](ui-dark-theme.md) | Agents + humans | Dark mode Slate surfaces, borders without elevation shadows, tooltips, scrollbars, solid CTA text |
| [ui-copy-style.md](ui-copy-style.md) | Agents + humans | Impersonal UI voice (no we/you); auth and panel copy |
| [content-draft-recovery.md](content-draft-recovery.md) | Agents + humans | Auto-save drafts + crash recovery on Filament Create/Edit |
| [resource-edit-audit.md](resource-edit-audit.md) | Agents + humans | Resource Edited By / Edited At attribution and table recency |
| [backups-and-danger-zone.md](backups-and-danger-zone.md) | Agents + humans | Backup catalog, restore tokens, guest restore, profile Danger Zone |
| [service-status.md](service-status.md) | Agents + humans | Tools Service Status page, health probes, uptime history |
| [active-sessions.md](active-sessions.md) | Agents + humans | Profile Active Sessions list, revoke, user-agent parsing |
| [household-access.md](household-access.md) | Agents + humans | Household roles, receipt attribution, family WhatsApp login, expense ACL |
| [recurrings.md](recurrings.md) | Agents + humans | Reminder-first recurring bills/subscriptions/transfers |
| [git-workflow.md](git-workflow.md) | Agents + humans | Feature/fix branches → PR → main; staging/production promotion |

## Agent assets (outside `docs/`)

| Path | Purpose |
|------|---------|
| `AGENTS.md` | Codex's always-loaded mode contract and project gates, followed by Laravel Boost guidelines |
| `.codex/` | Codex workflow, verification matrix, Plan template/local plans, and project MCP configuration |
| `.agents/AGENTS.md` | Antigravity's consolidated project rules |
| `.agents/skills/` | Repository skills surfaced to Codex and used by Antigravity |
| `.cursorrules` | Cursor hard coding / security directives |
| `.cursor/rules/*.mdc` | Cursor scoped always-on / glob rules |
| `.cursor/skills/` | Cursor domain and framework skill mirrors |
