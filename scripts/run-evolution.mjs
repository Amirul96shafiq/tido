import { execFileSync, spawn } from "node:child_process";
import fs from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const evolutionPath = path.resolve(
    process.env.EVOLUTION_PATH?.trim() || path.join("..", "evolution-api"),
);

if (!fs.existsSync(evolutionPath)) {
    console.error(`Evolution API not found at: ${evolutionPath}`);
    console.error("");
    console.error("Clone it next to tido (recommended):");
    console.error(
        "  cd .. && git clone https://github.com/evolution-foundation/evolution-api.git",
    );
    console.error("");
    console.error("Or set EVOLUTION_PATH to your clone, then retry:");
    console.error("  EVOLUTION_PATH=/path/to/evolution-api npm run evolution");
    console.error("");
    console.error("Full guide: docs/evolution-local-windows.md");
    process.exit(1);
}

const packageJson = path.join(evolutionPath, "package.json");

if (!fs.existsSync(packageJson)) {
    console.error(`No package.json in ${evolutionPath}`);
    process.exit(1);
}

const tidoRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), "..");
const inspectPreload = path.join(
    tidoRoot,
    "scripts",
    "evolution-console-inspect.cjs",
);
const tsxCli = path.join(
    evolutionPath,
    "node_modules",
    "tsx",
    "dist",
    "cli.mjs",
);
const evolutionLogPath = path.join(
    tidoRoot,
    "storage",
    "logs",
    "evolution-api.log",
);

if (!fs.existsSync(tsxCli)) {
    console.error(`tsx CLI not found at: ${tsxCli}`);
    console.error("Run npm install inside the Evolution API clone.");
    process.exit(1);
}

if (!fs.existsSync(inspectPreload)) {
    console.error(`Missing console inspect preload: ${inspectPreload}`);
    process.exit(1);
}

function attachOutput(stream, destinations) {
    stream.on("data", (chunk) => {
        for (const destination of destinations) {
            destination.write(chunk);
        }
    });
}

function isPidAlive(pid) {
    if (!Number.isInteger(pid) || pid <= 0) {
        return false;
    }

    try {
        process.kill(pid, 0);

        return true;
    } catch {
        return false;
    }
}

function sleepSync(ms) {
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

function evolutionLockPath() {
    return path.join(tmpdir(), "tido-evolution.lock");
}

function releaseEvolutionLock(lockPath) {
    try {
        const owner = Number.parseInt(
            fs.readFileSync(lockPath, "utf8").trim(),
            10,
        );
        if (owner === process.pid) {
            fs.unlinkSync(lockPath);
        }
    } catch {
        // already released
    }
}

function acquireEvolutionLock() {
    const lockPath = evolutionLockPath();

    for (let attempt = 0; attempt < 4; attempt++) {
        try {
            fs.writeFileSync(lockPath, String(process.pid), { flag: "wx" });

            return lockPath;
        } catch (error) {
            if (error.code !== "EEXIST") {
                throw error;
            }

            const owner = Number.parseInt(
                fs.readFileSync(lockPath, "utf8").trim(),
                10,
            );
            if (
                Number.isInteger(owner) &&
                owner !== process.pid &&
                isPidAlive(owner)
            ) {
                console.log(
                    `Stopping other Evolution launcher ${owner} (avoids WhatsApp session replace)`,
                );
                killPidTree(owner);
                sleepSync(500);
            }

            try {
                fs.unlinkSync(lockPath);
            } catch {
                // raced
            }
        }
    }

    fs.writeFileSync(lockPath, String(process.pid));

    return lockPath;
}

function killPidTree(pid) {
    if (!Number.isInteger(pid) || pid <= 0) {
        return;
    }

    try {
        if (process.platform === "win32") {
            execFileSync("taskkill.exe", ["/PID", String(pid), "/T", "/F"], {
                stdio: "ignore",
                windowsHide: true,
            });
        } else {
            process.kill(pid, "SIGTERM");
        }
    } catch {
        // already gone
    }
}

function windowsPidsMatching(needle) {
    const script = [
        `$needle = ${JSON.stringify(needle.toLowerCase())}`,
        "Get-CimInstance Win32_Process | Where-Object {",
        '  $_.CommandLine -and ($_.Name -eq "node.exe" -or $_.Name -eq "tsx.exe") -and',
        "  $_.CommandLine.ToLower().Contains($needle) -and",
        "  -not $_.CommandLine.ToLower().Contains('concurrently')",
        "} | Select-Object -ExpandProperty ProcessId",
    ].join("\n");

    try {
        const out = execFileSync(
            "powershell.exe",
            ["-NoProfile", "-Command", script],
            {
                encoding: "utf8",
                timeout: 20000,
                windowsHide: true,
                stdio: ["ignore", "pipe", "pipe"],
            },
        );

        return out
            .split(/\r?\n/)
            .map((line) => Number(line.trim()))
            .filter((pid) => Number.isInteger(pid) && pid > 0);
    } catch {
        return [];
    }
}

function killStaleEvolutionProcesses() {
    if (process.platform !== "win32") {
        return [];
    }

    const launcher = fileURLToPath(import.meta.url);
    const needles = new Set([
        evolutionPath,
        evolutionPath.replaceAll("\\", "/"),
        evolutionPath.replaceAll("/", "\\"),
        launcher,
        launcher.replaceAll("\\", "/"),
        "run-evolution.mjs",
        "evolution-console-inspect.cjs",
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

    const stale = [...pids];
    const deadline = Date.now() + 8000;
    while (Date.now() < deadline && stale.some((pid) => isPidAlive(pid))) {
        sleepSync(250);
    }

    return stale;
}

console.log(`Starting Evolution API from ${evolutionPath}`);
console.log(
    "Using: tsx ./src/main.ts (no watch — avoids leftover WhatsApp sockets)",
);
console.log(`Full stdout/stderr also written to ${evolutionLogPath}`);

const lockPath = acquireEvolutionLock();
try {
    killStaleEvolutionProcesses();
} catch (error) {
    console.error(
        `Stale Evolution cleanup failed: ${error.message} (continuing)`,
    );
}

// WhatsApp Linked Devices label comes from Baileys DeviceProps:
//   browser[0] (CLIENT) = os string shown to the user
//   browser[1] (NAME)   = PlatformType (Chrome|Firefox|Desktop|…)
// With NAME=Chrome, WhatsApp always prefixes "Google Chrome (…)" — so a custom
// NAME like "tido App" is ignored (falls back to Chrome) and nothing changes.
// Use Desktop + full label in CLIENT for QR → "tido App (Evolution API)".
// Pairing-code auth requires Chrome platform id (Desktop fails "Couldn't link
// device"); Evolution forces Chrome for pairing and keeps CLIENT as the label.
const sessionPhoneClient =
    process.env.CONFIG_SESSION_PHONE_CLIENT?.trim() ||
    "tido App (Evolution API)";
const sessionPhoneName =
    process.env.CONFIG_SESSION_PHONE_NAME?.trim() || "Desktop";

console.log(
    `Linked device identity: browser=["${sessionPhoneClient}", "${sessionPhoneName}", …]`,
);

fs.mkdirSync(path.dirname(evolutionLogPath), { recursive: true });
const evolutionLog = fs.createWriteStream(evolutionLogPath, { flags: "a" });
evolutionLog.write(`\n--- ${new Date().toISOString()} Evolution start ---\n`);

const child = spawn(
    process.execPath,
    ["--require", inspectPreload, tsxCli, "./src/main.ts"],
    {
        cwd: evolutionPath,
        stdio: ["inherit", "pipe", "pipe"],
        env: {
            ...process.env,
            CONFIG_SESSION_PHONE_CLIENT: sessionPhoneClient,
            CONFIG_SESSION_PHONE_NAME: sessionPhoneName,
        },
    },
);

if (child.stdout) {
    attachOutput(child.stdout, [process.stdout, evolutionLog]);
}

if (child.stderr) {
    attachOutput(child.stderr, [process.stderr, evolutionLog]);
}

child.on("error", (error) => {
    console.error("Failed to start Evolution:", error.message);
    evolutionLog.end();
    releaseEvolutionLock(lockPath);
    process.exit(1);
});

child.on("exit", (code, signal) => {
    evolutionLog.end();
    releaseEvolutionLock(lockPath);

    if (signal) {
        process.exit(1);
    }

    process.exit(code ?? 0);
});

for (const signal of ["SIGINT", "SIGTERM"]) {
    process.on(signal, () => {
        releaseEvolutionLock(lockPath);
        if (child.pid) {
            killPidTree(child.pid);
        } else {
            child.kill(signal);
        }
    });
}
