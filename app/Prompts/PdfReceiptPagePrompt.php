<?php

declare(strict_types=1);

namespace App\Prompts;

final class PdfReceiptPagePrompt
{
    public static function build(int $pageNumber, int $totalPages): string
    {
        $pageNumber = max(1, $pageNumber);
        $totalPages = max($pageNumber, $totalPages);

        return implode("\n\n", [
            ReceiptExtractionPrompt::build(),
            'PDF page-specific overrides for this extraction step:',
            sprintf(
                'This image is page %d of %d from one PDF receipt or invoice.',
                $pageNumber,
                $totalPages,
            ),
            implode("\n", [
                'Extract only information visible on this page.',
                'Use null for merchant, invoice number, date, payment method, or totals that are not visible.',
                'Do not invent missing values or copy carried-forward totals into line items.',
                'Preserve every genuine line item visible on this page.',
            ]),
        ]);
    }
}
