<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

final class PdfPageRenderer
{
    public function __construct(
        private readonly PdfPageInspector $pdfPageInspector,
    ) {}

    /**
     * @return list<string>
     */
    public function render(string $pdfContents): array
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tido_pdf_'.Str::uuid();
        $inputPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'input.pdf';
        $outputPrefix = $temporaryDirectory.DIRECTORY_SEPARATOR.'page';

        File::ensureDirectoryExists($temporaryDirectory);

        try {
            if (File::put($inputPath, $pdfContents) === false) {
                throw new RuntimeException('Unable to write the temporary PDF file.');
            }

            $pageCount = $this->pdfPageInspector->pageCountFromPath($inputPath);
            $renderArguments = [
                '-jpeg',
                '-r',
                (string) max(72, (int) config('services.documents.pdf_render_dpi', 144)),
                '-f',
                '1',
                '-l',
                (string) $pageCount,
                $inputPath,
                $outputPrefix,
            ];
            $rendererBinary = $this->configuredBinary('pdftocairo_binary', 'pdftocairo');
            $result = $this->runRenderer($rendererBinary, $renderArguments);

            if (
                $result->failed()
                && PdfInspectionException::reasonFromProcessOutput($result->errorOutput())
                    === PdfInspectionException::DEPENDENCY_MISSING
            ) {
                $fallbackBinary = $this->configuredBinary('pdftoppm_binary', 'pdftoppm');

                if ($fallbackBinary !== $rendererBinary) {
                    $result = $this->runRenderer($fallbackBinary, $renderArguments);
                }
            }

            if ($result->failed()) {
                throw new PdfInspectionException(
                    PdfInspectionException::reasonFromProcessOutput($result->errorOutput()),
                    trim($result->errorOutput()) ?: 'Unable to render the PDF file.',
                );
            }

            $pagePaths = glob($outputPrefix.'-*.jpg') ?: [];
            natsort($pagePaths);
            $pagePaths = array_values($pagePaths);

            if (count($pagePaths) !== $pageCount) {
                throw new RuntimeException('The rendered PDF page count does not match the inspected page count.');
            }

            return array_map(
                static fn (string $pagePath): string => File::get($pagePath),
                $pagePaths,
            );
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runRenderer(string $binary, array $arguments): ProcessResult
    {
        return Process::timeout(max(
            1,
            (int) config('services.documents.pdf_render_timeout', 60),
        ))->run([$binary, ...$arguments]);
    }

    private function configuredBinary(string $key, string $default): string
    {
        $configured = trim((string) config('services.documents.'.$key, $default));

        return $configured === '' || str_contains($configured, '<poppler-install-folder>')
            ? $default
            : $configured;
    }
}
