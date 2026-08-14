import { execFileSync, spawn } from 'node:child_process';
import { copyFileSync, existsSync, linkSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const preload = join(root, 'scripts', 'set-process-title.cjs');
const concurrentlyJs = join(root, 'node_modules', 'concurrently', 'dist', 'bin', 'concurrently.js');
const spawnWithParentPs1 = join(root, 'scripts', 'win-spawn-with-parent.ps1');
const cacheDir = join(root, 'node_modules', '.cache', 'tido-terminal');
const titlePattern = /^[A-Za-z][A-Za-z0-9_-]{0,31}$/;

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

function forwardChild(child, killPid = null) {
    child.on('error', (error) => {
        console.error(error.message);
        process.exit(1);
    });

    child.on('exit', (code, signal) => {
        process.exit(signal ? 1 : (code ?? 0));
    });

    for (const signal of ['SIGINT', 'SIGTERM']) {
        process.on(signal, () => {
            const target = killPid ?? child.pid;
            if (target) {
                killPidTree(target);
            } else {
                child.kill(signal);
            }
        });
    }
}

function resolveWindowsLauncher(title) {
    mkdirSync(cacheDir, { recursive: true });

    const launcher = join(cacheDir, `${title}.exe`);
    const source = process.execPath;

    if (existsSync(launcher)) {
        try {
            if (statSync(launcher).size === statSync(source).size) {
                return launcher;
            }
        } catch {
            // recreate below
        }
    }

    try {
        linkSync(source, launcher);

        return launcher;
    } catch (error) {
        if (error.code === 'EEXIST' && existsSync(launcher)) {
            return launcher;
        }
    }

    try {
        copyFileSync(source, launcher);
    } catch (error) {
        if (existsSync(launcher) && (error.code === 'EBUSY' || error.code === 'EPERM')) {
            return launcher;
        }

        throw error;
    }

    return launcher;
}

function spawnConcurrently(exe, extraArgs, env) {
    return spawn(exe, ['--require', preload, concurrentlyJs, ...extraArgs], {
        stdio: 'inherit',
        env,
    });
}

function trySpawnAttached(title, extraArgs, env) {
    const launcher = resolveWindowsLauncher(title);
    const stamp = `${process.pid}-${Date.now()}`;
    const specFile = join(tmpdir(), `tido-spec-${stamp}.json`);
    const pidFile = join(tmpdir(), `tido-pid-${stamp}.txt`);
    const resultFile = join(tmpdir(), `tido-spawn-${stamp}.json`);

    writeFileSync(
        specFile,
        JSON.stringify({
            executable: launcher,
            args: ['--require', preload, concurrentlyJs, ...extraArgs],
            pidFile,
            debugFile: resultFile,
            cwd: process.cwd(),
        }),
    );

    try {
        execFileSync(
            'powershell.exe',
            ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', spawnWithParentPs1, '-SpecFile', specFile],
            {
                stdio: 'inherit',
                windowsHide: true,
                env,
                timeout: 30000,
            },
        );
    } catch {
        return null;
    }

    let result = { ok: false };
    try {
        result = JSON.parse(readFileSync(resultFile, 'utf8'));
    } catch {
        return null;
    }

    const childPid = Number.parseInt(readFileSync(pidFile, 'utf8').trim(), 10);
    if (!result.ok || !Number.isInteger(childPid) || childPid <= 0) {
        return null;
    }

    return childPid;
}

const title = process.argv[2];
const [command, ...args] = process.argv.slice(3);

if (!title || !titlePattern.test(title) || !command) {
    console.error('Usage: node scripts/run-named.mjs <title> <command> [...args]');
    process.exit(1);
}

const env = {
    ...process.env,
    TIDO_TERMINAL_TITLE: title,
};

if (command === 'concurrently' && process.platform === 'win32') {
    const attachedPid = trySpawnAttached(title, args, env);
    if (attachedPid) {
        const waiter = spawn(
            'powershell.exe',
            ['-NoProfile', '-Command', `Wait-Process -Id ${attachedPid}`],
            { stdio: 'ignore', windowsHide: true, env },
        );
        forwardChild(waiter, attachedPid);
    } else {
        forwardChild(spawnConcurrently(resolveWindowsLauncher(title), args, env));
    }
} else if (command === 'concurrently') {
    process.title = title;
    forwardChild(spawnConcurrently(process.execPath, args, env));
} else {
    process.title = title;
    forwardChild(spawn(command, args, { stdio: 'inherit', shell: true, env }));
}
