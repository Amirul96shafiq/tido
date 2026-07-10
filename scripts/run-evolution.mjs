import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const evolutionPath = path.resolve(
    process.env.EVOLUTION_PATH?.trim() || path.join('..', 'evolution-api'),
);

if (!fs.existsSync(evolutionPath)) {
    console.error(`Evolution API not found at: ${evolutionPath}`);
    console.error('');
    console.error('Clone it next to tido (recommended):');
    console.error('  cd .. && git clone https://github.com/evolution-foundation/evolution-api.git');
    console.error('');
    console.error('Or set EVOLUTION_PATH to your clone, then retry:');
    console.error('  EVOLUTION_PATH=/path/to/evolution-api npm run evolution');
    console.error('');
    console.error('Full guide: docs/evolution-local-windows.md');
    process.exit(1);
}

const packageJson = path.join(evolutionPath, 'package.json');

if (!fs.existsSync(packageJson)) {
    console.error(`No package.json in ${evolutionPath}`);
    process.exit(1);
}

const npmCmd = process.platform === 'win32' ? 'npm.cmd' : 'npm';

console.log(`Starting Evolution API from ${evolutionPath}`);
console.log('Using: npm run dev:server (fallback: start:prod if you change this script)');

// WhatsApp Linked Devices label comes from Baileys DeviceProps:
//   browser[0] (CLIENT) = os string shown to the user
//   browser[1] (NAME)   = PlatformType (Chrome|Firefox|Desktop|…)
// With NAME=Chrome, WhatsApp always prefixes "Google Chrome (…)" — so a custom
// NAME like "tido App" is ignored (falls back to Chrome) and nothing changes.
// Use Desktop + full label in CLIENT for "tido App (Evolution API)".
const sessionPhoneClient =
    process.env.CONFIG_SESSION_PHONE_CLIENT?.trim() || 'tido App (Evolution API)';
const sessionPhoneName = process.env.CONFIG_SESSION_PHONE_NAME?.trim() || 'Desktop';

console.log(`Linked device identity: browser=["${sessionPhoneClient}", "${sessionPhoneName}", …]`);

const child = spawn(npmCmd, ['run', 'dev:server'], {
    cwd: evolutionPath,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: {
        ...process.env,
        CONFIG_SESSION_PHONE_CLIENT: sessionPhoneClient,
        CONFIG_SESSION_PHONE_NAME: sessionPhoneName,
    },
});

child.on('error', (error) => {
    console.error('Failed to start Evolution:', error.message);
    process.exit(1);
});

child.on('exit', (code, signal) => {
    if (signal) {
        process.exit(1);
    }

    process.exit(code ?? 0);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        child.kill(signal);
    });
}
