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

const child = spawn(npmCmd, ['run', 'dev:server'], {
    cwd: evolutionPath,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: process.env,
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
