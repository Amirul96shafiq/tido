import { spawn } from 'node:child_process';
import process from 'node:process';

const defaultHost = 'http://127.0.0.1:11434';
const ollamaHost = (process.env.OLLAMA_HOST?.trim() || defaultHost).replace(/\/$/, '');
const tagsUrl = `${ollamaHost}/api/tags`;

async function isOllamaRunning() {
    try {
        const response = await fetch(tagsUrl, { signal: AbortSignal.timeout(3000) });

        return response.ok;
    } catch {
        return false;
    }
}

async function main() {
    if (await isOllamaRunning()) {
        console.log(`Ollama is already running at ${ollamaHost}`);
        console.log('Skipping ollama serve.');

        return;
    }

    console.log(`Starting Ollama at ${ollamaHost}`);
    console.log('Using: ollama serve');
    console.log('');
    console.log('Pull the vision model once if needed:');
    console.log('  ollama pull qwen2.5vl:7b');
    console.log('');
    console.log('Full guide: docs/ollama-setup.md');

    const child = spawn('ollama', ['serve'], {
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });

    child.on('error', (error) => {
        if (error.code === 'ENOENT') {
            console.error('Ollama CLI not found. Install from https://ollama.com/download');
            console.error('');
            console.error('Full guide: docs/ollama-setup.md');
        } else {
            console.error('Failed to start Ollama:', error.message);
        }

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
}

main();
