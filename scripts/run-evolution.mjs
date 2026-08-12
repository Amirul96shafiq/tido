import { execFileSync, spawn } from 'node:child_process';
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

function killPidTree(pid) {
    if (!Number.isInteger(pid) || pid <= 0) {
        return;
    }

    try {
        if (process.platform === 'win32') {
            execFileSync('taskkill.exe', ['/PID', String(pid), '/T', '/F'], {
                stdio: 'ignore',
                windowsHide: true,
            });
        } else {
            process.kill(pid, 'SIGTERM');
        }
    } catch {
        // already gone
    }
}

function windowsPidsMatching(needle) {
    const script = [
        `$needle = ${JSON.stringify(needle.toLowerCase())}`,
        'Get-CimInstance Win32_Process | Where-Object {',
        '  $_.CommandLine -and ($_.Name -eq "node.exe" -or $_.Name -eq "tsx.exe") -and',
        '  $_.CommandLine.ToLower().Contains($needle)',
        '} | Select-Object -ExpandProperty ProcessId',
    ].join('; ');

    try {
        const out = execFileSync('powershell.exe', ['-NoProfile', '-Command', script], {
            encoding: 'utf8',
            timeout: 20000,
            windowsHide: true,
        });

        return out
            .split(/\r?\n/)
            .map((line) => Number(line.trim()))
            .filter((pid) => Number.isInteger(pid) && pid > 0);
    } catch {
        return [];
    }
}

function killStaleEvolutionProcesses() {
    if (process.platform !== 'win32') {
        return [];
    }

    const needles = new Set([
        evolutionPath,
        evolutionPath.replaceAll('\\', '/'),
        evolutionPath.replaceAll('/', '\\'),
    ]);

    const pids = new Set();

    for (const needle of needles) {
        for (const pid of windowsPidsMatching(needle)) {
            if (pid !== process.pid && pid !== process.ppid) {
                pids.add(pid);
            }
        }
    }

    for (const pid of pids) {
        console.log(`Stopping stale Evolution process ${pid}`);
        killPidTree(pid);
    }

    return [...pids];
}

console.log(`Starting Evolution API from ${evolutionPath}`);
console.log('Using: npm run start (tsx, no watch — avoids leftover WhatsApp sockets)');

killStaleEvolutionProcesses();

// WhatsApp Linked Devices label comes from Baileys DeviceProps:
//   browser[0] (CLIENT) = os string shown to the user
//   browser[1] (NAME)   = PlatformType (Chrome|Firefox|Desktop|…)
// With NAME=Chrome, WhatsApp always prefixes "Google Chrome (…)" — so a custom
// NAME like "tido App" is ignored (falls back to Chrome) and nothing changes.
// Use Desktop + full label in CLIENT for QR → "tido App (Evolution API)".
// Pairing-code auth requires Chrome platform id (Desktop fails "Couldn't link
// device"); Evolution forces Chrome for pairing and keeps CLIENT as the label.
const sessionPhoneClient =
    process.env.CONFIG_SESSION_PHONE_CLIENT?.trim() || 'tido App (Evolution API)';
const sessionPhoneName = process.env.CONFIG_SESSION_PHONE_NAME?.trim() || 'Desktop';

console.log(`Linked device identity: browser=["${sessionPhoneClient}", "${sessionPhoneName}", …]`);

const child = spawn(npmCmd, ['run', 'start'], {
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
        if (child.pid) {
            killPidTree(child.pid);
        } else {
            child.kill(signal);
        }
    });
}
