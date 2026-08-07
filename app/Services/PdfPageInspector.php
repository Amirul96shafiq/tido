<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final class PdfPageInspector
{
    public function pageCount(string $pdfContents): int
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'tido_pdf_');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary PDF file.');
        }

        try {
            if (File::put($temporaryPath, $pdfContents) === false) {
                throw new RuntimeException('Unable to write the temporary PDF file.');
            }

            return $this->pageCountFromPath($temporaryPath);
        } finally {
            File::delete($temporaryPath);
        }
    }

    public function pageCountFromPath(string $pdfPath): int
    {
        $result = Process::timeout(max(
            1,
            (int) config('services.documents.pdf_inspection_timeout', 15),
        ))->run([
            (string) config('services.documents.pdfinfo_binary', 'pdfinfo'),
            $pdfPath,
        ]);

        if ($result->failed()) {
            $errorOutput = trim($result->errorOutput());
            $reason = PdfInspectionException::reasonFromProcessOutput($errorOutput);

            throw new PdfInspectionException(
                $reason,
                $errorOutput ?: 'Unable to inspect the PDF file.',
            );
        }

        if (preg_match('/^Pages:\s+(\d+)\s*$/mi', $result->output(), $matches) !== 1) {
            throw new PdfInspectionException(
                PdfInspectionException::UNREADABLE,
                'Unable to determine the PDF page count.',
            );
        }

        $pageCount = (int) $matches[1];

        if ($pageCount < 1) {
            throw new PdfInspectionException(
                PdfInspectionException::UNREADABLE,
                'The PDF file does not contain any pages.',
            );
        }

        return $pageCount;
    }
}
