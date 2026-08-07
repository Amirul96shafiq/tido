<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

final class PdfTextExtractor
{
    public function extract(string $pdfContents): string
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tido_pdf_text_'.Str::uuid();
        $inputPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'input.pdf';

        File::ensureDirectoryExists($temporaryDirectory);

        try {
            if (File::put($inputPath, $pdfContents) === false) {
                throw new RuntimeException('Unable to write the temporary PDF file for text extraction.');
            }

            $result = Process::timeout(max(
                1,
                (int) config('services.documents.pdf_text_timeout', 15),
            ))->run([
                (string) config('services.documents.pdftotext_binary', 'pdftotext'),
                '-layout',
                $inputPath,
                '-',
            ]);

            if ($result->failed()) {
                return '';
            }

            return trim($result->output());
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }
}
