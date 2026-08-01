<?php

declare(strict_types=1);

namespace App\Services;

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
            $result = Process::timeout(max(
                1,
                (int) config('services.documents.pdf_render_timeout', 60),
            ))->run([
                (string) config('services.documents.pdftocairo_binary', 'pdftocairo'),
                '-jpeg',
                '-r',
                (string) max(72, (int) config('services.documents.pdf_render_dpi', 144)),
                '-f',
                '1',
                '-l',
                (string) $pageCount,
                $inputPath,
                $outputPrefix,
            ]);

            if ($result->failed()) {
                throw new PdfInspectionException(
                    PdfInspectionException::UNREADABLE,
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
}
