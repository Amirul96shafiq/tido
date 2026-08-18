<?php

declare(strict_types=1);

namespace App\Services\Ollama;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final class PopplerDetector
{
    public const WINDOWS_DOWNLOAD_URL = 'https://github.com/oschwartz10612/poppler-windows/releases/latest';

    /** @var list<string>|null */
    private ?array $searchDirectories = null;

    /**
     * @return array{
     *     pdfinfo: string|null,
     *     pdftocairo: string|null,
     *     pdftotext: string|null,
     *     allFound: bool,
     * }
     */
    public function probe(): array
    {
        $complete = $this->completeInstall();

        if ($complete !== null) {
            return [
                'pdfinfo' => $complete['pdfinfo'],
                'pdftocairo' => $complete['pdftocairo'],
                'pdftotext' => $complete['pdftotext'],
                'allFound' => true,
            ];
        }

        $pdfinfo = $this->resolveBinary('pdfinfo');
        $pdftocairo = $this->resolveBinary('pdftocairo');
        $pdftotext = $this->resolveBinary('pdftotext');

        return [
            'pdfinfo' => $pdfinfo,
            'pdftocairo' => $pdftocairo,
            'pdftotext' => $pdftotext,
            'allFound' => filled($pdfinfo) && filled($pdftocairo) && filled($pdftotext),
        ];
    }

    /**
     * @return array{pdfinfo: string, pdftocairo: string, pdftotext: string}|null
     */
    private function completeInstall(): ?array
    {
        foreach ($this->pathDirectories() as $directory) {
            $pdfinfo = $this->binaryPath($directory, 'pdfinfo');
            $pdftocairo = $this->binaryPath($directory, 'pdftocairo');
            $pdftotext = $this->binaryPath($directory, 'pdftotext');

            if (
                ! File::exists($pdfinfo)
                || ! File::exists($pdftocairo)
                || ! File::exists($pdftotext)
            ) {
                continue;
            }

            if (
                $this->binaryResponds($pdfinfo)
                && $this->binaryResponds($pdftocairo)
                && $this->binaryResponds($pdftotext)
            ) {
                return [
                    'pdfinfo' => $pdfinfo,
                    'pdftocairo' => $pdftocairo,
                    'pdftotext' => $pdftotext,
                ];
            }
        }

        return null;
    }

    private function binaryPath(string $directory, string $name): string
    {
        $base = rtrim($directory, '\\/');

        if (PHP_OS_FAMILY === 'Windows') {
            $exe = $base.DIRECTORY_SEPARATOR.$name.'.exe';

            if (File::exists($exe)) {
                return $exe;
            }
        }

        return $base.DIRECTORY_SEPARATOR.$name;
    }

    private function resolveBinary(string $name): ?string
    {
        foreach ($this->pathCandidates($name) as $candidate) {
            if ($this->binaryResponds($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pathCandidates(string $name): array
    {
        $candidates = [];

        foreach ($this->pathDirectories() as $directory) {
            $base = rtrim($directory, '\\/');

            if (PHP_OS_FAMILY === 'Windows') {
                $candidates[] = $base.DIRECTORY_SEPARATOR.$name.'.exe';
                $candidates[] = $base.DIRECTORY_SEPARATOR.$name;
            } else {
                $candidates[] = $base.DIRECTORY_SEPARATOR.$name;
            }
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function pathDirectories(): array
    {
        if ($this->searchDirectories !== null) {
            return $this->searchDirectories;
        }

        $directories = array_merge(
            $this->splitPath($this->processPath()),
            $this->persistentWindowsPathDirectories(),
            $this->wellKnownWindowsDirectories(),
        );

        return $this->searchDirectories = array_values(array_unique(array_filter(
            $directories,
            fn (string $directory): bool => $directory !== '',
        )));
    }

    /**
     * @return list<string>
     */
    private function splitPath(string $path): array
    {
        return array_values(array_filter(
            array_map('trim', explode(PHP_OS_FAMILY === 'Windows' ? ';' : ':', $path)),
            fn (string $directory): bool => $directory !== '',
        ));
    }

    private function processPath(): string
    {
        return (string) (getenv('Path') ?: getenv('PATH') ?: '');
    }

    /**
     * @return list<string>
     */
    private function persistentWindowsPathDirectories(): array
    {
        if (PHP_OS_FAMILY !== 'Windows' || app()->runningUnitTests()) {
            return [];
        }

        return array_merge(
            $this->splitPath($this->windowsRegistryPath('HKCU\\Environment')),
            $this->splitPath($this->windowsRegistryPath('HKLM\\SYSTEM\\CurrentControlSet\\Control\\Session Manager\\Environment')),
        );
    }

    /**
     * @return list<string>
     */
    private function wellKnownWindowsDirectories(): array
    {
        if (PHP_OS_FAMILY !== 'Windows' || app()->runningUnitTests()) {
            return [];
        }

        $roots = array_values(array_filter([
            getenv('USERPROFILE') ?: null,
            (getenv('USERPROFILE') ?: '').DIRECTORY_SEPARATOR.'Downloads',
            getenv('LOCALAPPDATA') ?: null,
            'G:\\Apps',
            'C:\\poppler',
        ]));

        $directories = [];

        foreach ($roots as $root) {
            foreach (glob($root.DIRECTORY_SEPARATOR.'poppler*'.DIRECTORY_SEPARATOR.'Library'.DIRECTORY_SEPARATOR.'bin', GLOB_ONLYDIR) ?: [] as $directory) {
                $directories[] = $directory;
            }
        }

        return $directories;
    }

    private function windowsRegistryPath(string $key): string
    {
        $output = [];
        $exitCode = 0;
        exec('reg query "'.$key.'" /v Path 2>NUL', $output, $exitCode);

        if ($exitCode !== 0) {
            return '';
        }

        $body = implode("\n", $output);

        if (! preg_match('/Path\s+REG_\w+\s+(.+)$/im', $body, $matches)) {
            return '';
        }

        return $this->expandWindowsPath(trim($matches[1]));
    }

    private function expandWindowsPath(string $path): string
    {
        return (string) preg_replace_callback(
            '/%([^%]+)%/',
            function (array $matches): string {
                $value = getenv($matches[1]);

                return is_string($value) && $value !== '' ? $value : $matches[0];
            },
            $path,
        );
    }

    private function binaryResponds(string $path): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        $result = Process::timeout(5)->run([$path, '-v']);

        return $result->exitCode() === 0 || $result->output() !== '' || $result->errorOutput() !== '';
    }
}
