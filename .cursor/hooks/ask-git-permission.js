#!/usr/bin/env node
/**
 * Gate only publish/write git actions (push, commit, gh pr create/merge).
 * Read-only git/gh inspection is allowed.
 *
 * NOTE: Cursor hook permission "ask" is unreliable; pair with Allowlist
 * (do not allowlist push/commit/gh pr) and Auto-review block_instructions.
 */
const fs = require('fs');

let input = {};

try {
    input = JSON.parse(fs.readFileSync(0, 'utf8'));
} catch {
    process.stdout.write(JSON.stringify({ permission: 'allow' }));
    process.exit(0);
}

const command = String(input.command ?? '');

/** True when the shell command would publish or create a PR. */
const needsApproval =
    /\bgit(?:\.exe)?\s+push\b/i.test(command) ||
    /\bgit(?:\.exe)?\s+commit\b/i.test(command) ||
    /\bgh(?:\.exe)?\s+pr\s+(create|merge|edit|close|ready|reopen)\b/i.test(command);

if (needsApproval) {
    process.stdout.write(
        JSON.stringify({
            permission: 'ask',
            user_message:
                'This would push or open/update a PR. Review the content, then Allow if you approve.',
            agent_message:
                'Push/commit/PR write requires user approval. Do not retry without explicit approval.',
        }),
    );
    process.exit(0);
}

process.stdout.write(JSON.stringify({ permission: 'allow' }));
process.exit(0);
