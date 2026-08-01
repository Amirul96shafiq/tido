<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ReceiptDocumentPreparer
{
    public function __construct(
        private readonly ReceiptImagePreparer $imagePreparer,
        private readonly PdfPageRenderer $pdfRenderer,
    ) {}

    /**
     * @return list<string>
     */
    public function prepare(Invoice $invoice): array
    {
        if (blank($invoice->image_path) || ! Storage::exists($invoice->image_path)) {
            throw new RuntimeException('The receipt document does not exist in storage.');
        }

        $contents = (string) Storage::get($invoice->image_path);

        if ($invoice->file_mime_type === 'application/pdf') {
            return array_map(
                fn (string $page): string => $this->imagePreparer->toBase64($page),
                $this->pdfRenderer->render($contents),
            );
        }

        return [$this->imagePreparer->toBase64($contents)];
    }
}
