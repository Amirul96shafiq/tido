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
        private readonly PdfTextExtractor $pdfTextExtractor,
    ) {}

    /**
     * @return list<string>
     */
    public function prepare(Invoice $invoice): array
    {
        $contents = $this->contents($invoice);

        if ($invoice->file_mime_type === 'application/pdf') {
            return array_map(
                fn (string $page): string => $this->imagePreparer->toBase64($page),
                $this->pdfRenderer->render($contents),
            );
        }

        return [$this->imagePreparer->toBase64($contents)];
    }

    public function extractText(Invoice $invoice): ?string
    {
        if ($invoice->file_mime_type !== 'application/pdf') {
            return null;
        }

        return $this->pdfTextExtractor->extract($this->contents($invoice));
    }

    private function contents(Invoice $invoice): string
    {
        if (blank($invoice->image_path) || ! Storage::exists($invoice->image_path)) {
            throw new RuntimeException('The receipt document does not exist in storage.');
        }

        return (string) Storage::get($invoice->image_path);
    }
}
