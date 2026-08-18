<?php

declare(strict_types=1);

namespace App\Services\Ollama;

use App\Enums\OllamaDetectionState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class OllamaDetector
{
    public function __construct(
        private readonly OllamaSettings $settings,
    ) {}

    /**
     * @return array{
     *     state: OllamaDetectionState,
     *     host: string,
     *     latencyMs: int,
     *     modelCount: int,
     *     cliPath: string|null,
     *     message: string,
     * }
     */
    public function probe(?string $host = null): array
    {
        $host = rtrim($host ?? $this->settings->host(), '/');
        $cliPath = $this->resolveCliPath();
        $isLocalHost = $this->isLocalHost($host);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(5)->get("{$host}/api/tags");
            $latencyMs = $this->elapsedMs($startedAt);

            if ($response->successful()) {
                $models = data_get($response->json(), 'models');
                $modelCount = is_array($models) ? count($models) : 0;

                return [
                    'state' => OllamaDetectionState::Running,
                    'host' => $host,
                    'latencyMs' => $latencyMs,
                    'modelCount' => $modelCount,
                    'cliPath' => $cliPath,
                    'message' => $modelCount === 0
                        ? 'Ollama is running but no models are installed yet.'
                        : 'Ollama is connected.',
                ];
            }

            return $this->unreachableResult(
                host: $host,
                cliPath: $cliPath,
                isLocalHost: $isLocalHost,
                latencyMs: $latencyMs,
                message: 'Ollama responded with HTTP '.$response->status().'.',
            );
        } catch (Throwable $throwable) {
            return $this->unreachableResult(
                host: $host,
                cliPath: $cliPath,
                isLocalHost: $isLocalHost,
                latencyMs: $this->elapsedMs($startedAt),
                message: 'Ollama is unreachable: '.$throwable->getMessage(),
            );
        }
    }

    public function tryStart(): bool
    {
        if ($this->resolveCliPath() === null) {
            return false;
        }

        try {
            Process::timeout(3)->start($this->startCommand());
        } catch (Throwable) {
            return false;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            usleep(500_000);

            $probe = $this->probe();

            if ($probe['state'] === OllamaDetectionState::Running) {
                return true;
            }
        }

        return false;
    }

    public function resolveCliPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA') ?: null;

            if (is_string($localAppData) && $localAppData !== '') {
                $defaultPath = $localAppData.'\\Programs\\Ollama\\ollama.exe';

                if (is_file($defaultPath)) {
                    return $defaultPath;
                }
            }

            $whereResult = Process::timeout(5)->run(['where', 'ollama']);

            if ($whereResult->successful()) {
                $firstLine = trim(strtok($whereResult->output(), PHP_EOL) ?: '');

                if ($firstLine !== '') {
                    return $firstLine;
                }
            }

            return null;
        }

        $whichResult = Process::timeout(5)->run(['which', 'ollama']);

        if ($whichResult->successful()) {
            $path = trim($whichResult->output());

            return $path !== '' ? $path : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function startCommand(): array
    {
        $cliPath = $this->resolveCliPath();

        if ($cliPath !== null && $cliPath !== 'ollama') {
            return [$cliPath, 'serve'];
        }

        return ['ollama', 'serve'];
    }

    private function isLocalHost(string $host): bool
    {
        $normalized = strtolower(rtrim($host, '/'));

        return in_array($normalized, [
            'http://127.0.0.1:11434',
            'http://localhost:11434',
            'http://0.0.0.0:11434',
        ], true);
    }

    /**
     * @return array{
     *     state: OllamaDetectionState,
     *     host: string,
     *     latencyMs: int,
     *     modelCount: int,
     *     cliPath: string|null,
     *     message: string,
     * }
     */
    private function unreachableResult(
        string $host,
        ?string $cliPath,
        bool $isLocalHost,
        int $latencyMs,
        string $message,
    ): array {
        if (! $isLocalHost) {
            return [
                'state' => OllamaDetectionState::RemoteUnreachable,
                'host' => $host,
                'latencyMs' => $latencyMs,
                'modelCount' => 0,
                'cliPath' => $cliPath,
                'message' => $message,
            ];
        }

        if ($cliPath !== null) {
            return [
                'state' => OllamaDetectionState::InstalledStopped,
                'host' => $host,
                'latencyMs' => $latencyMs,
                'modelCount' => 0,
                'cliPath' => $cliPath,
                'message' => 'Ollama is installed on this PC but is not responding.',
            ];
        }

        return [
            'state' => OllamaDetectionState::NotInstalled,
            'host' => $host,
            'latencyMs' => $latencyMs,
            'modelCount' => 0,
            'cliPath' => null,
            'message' => 'Ollama was not detected on this PC.',
        ];
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
