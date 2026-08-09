<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
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
    public function prepare(Expense $expense): array
    {
        $contents = $this->contents($expense);

        if ($expense->file_mime_type === 'application/pdf') {
            return array_map(
                fn (string $page): string => $this->imagePreparer->toBase64($page),
                $this->pdfRenderer->render($contents),
            );
        }

        return [$this->imagePreparer->toBase64($contents)];
    }

    public function extractText(Expense $expense): ?string
    {
        if ($expense->file_mime_type !== 'application/pdf') {
            return null;
        }

        return $this->pdfTextExtractor->extract($this->contents($expense));
    }

    private function contents(Expense $expense): string
    {
        if (blank($expense->image_path) || ! Storage::exists($expense->image_path)) {
            throw new RuntimeException('The receipt document does not exist in storage.');
        }

        return (string) Storage::get($expense->image_path);
    }
}
