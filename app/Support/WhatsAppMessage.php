<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\MoneyDisplay;
use App\Models\PaymentMethod;

final class WhatsAppMessage
{
    public const FOOTER = '— Powered by *tido*';

    /**
     * Compose a professional WhatsApp auto-message: header, body, footer.
     */
    public static function compose(
        string $emoji,
        string $title,
        string $body,
        string $footer = self::FOOTER,
    ): string {
        $header = trim($emoji.' *'.trim($title).'*');
        $body = trim($body);
        $footer = trim($footer);

        $sections = array_values(array_filter(
            [$header, $body, $footer],
            static fn (string $section): bool => $section !== '',
        ));

        return implode("\n\n", $sections);
    }

    public static function help(): string
    {
        return self::compose(
            '🤖',
            'Help',
            implode("\n", [
                'Use one of the approaches below in WhatsApp chat to start AI parsing and store it as an expense:',
                '- Upload a *document(s)* or *image(s)*',
                '- Type a *manual expense text* (type *manual* to learn more)',
                '- Type *spend* or *total* for spending summary (type *finance others* to learn more)',
            ]),
        );
    }

    public static function financeKeywords(): string
    {
        return self::compose(
            '📈',
            'Finance Keywords',
            implode("\n", [
                'Use one of the keywords below in WhatsApp chat to retrieve according details:',
                '- *spend* or *total* — monthly spending summary',
                '- *spend labels* — label breakdown (up to 8)',
                '- *spend merchants* — top 5 merchants',
                '- *spend budgets* — all active budgets with status',
                '- *spend trend* — last 6 months spending',
                '- *spend payment* — spending by payment method',
                '- *spend recent* — last 5 receipts',
                '- *spend last month* — summary for the previous month',
                '- *spend march* / *spend 2025-03* — summary for a specific month',
            ]),
        );
    }

    public static function manualApproach(): string
    {
        $paymentMethodLines = PaymentMethod::orderedForSelect()
            ->map(static fn (PaymentMethod $method): string => '- '.$method->name)
            ->implode("\n");

        $body = implode("\n", array_filter([
            'Type the exact format below in this WhatsApp chat:',
            '',
            '[Expense title], [Payment method];',
            '[Line item 1  label], [quantity], [total price];',
            '...',
            '',
            'Sample:',
            '',
            'ASNB Investment, FPX;',
            'Amanah Saham Bumiputera (Class A), 1, 200;',
            '',
            'Payment method supported:',
            $paymentMethodLines !== '' ? $paymentMethodLines : null,
        ], static fn (?string $line): bool => $line !== null));

        return self::compose('💬', 'Manual Approach', $body);
    }

    public static function receiptUploadFailed(int $attempt, int $maxAttempts = 3): string
    {
        $attempt = max(1, min($attempt, $maxAttempts));

        $title = sprintf('Upload failed (attempt %d of %d)', $attempt, $maxAttempts);

        if ($attempt < $maxAttempts) {
            $body = "Download failed. The file could not be retrieved from WhatsApp.\n\nAutomatic retry in about 60 seconds.";
        } else {
            $body = "Download failed after the final attempt. The file could not be retrieved from WhatsApp.\n\nResend the document to try again.";
        }

        return self::compose('❌', $title, $body);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public static function documentReceived(int $count, array $documents = []): string
    {
        $count = max(1, $count);

        $rejectedDocuments = collect($documents)
            ->whereIn('status', ['rejected', 'failed'])
            ->values();

        if ($rejectedDocuments->isEmpty()) {
            return self::compose(
                '📥',
                'Document received',
                sprintf('A total of *%d* file(s) saved and queued for AI parsing.', $count),
            );
        }

        $acceptedCount = collect($documents)->where('status', 'accepted')->count();
        $unsupportedDocuments = $rejectedDocuments->where('status', 'rejected')->values();
        $failedDocuments = $rejectedDocuments->where('status', 'failed')->values();
        $lines = [
            sprintf('A total of *%d* file(s) received.', $count),
            sprintf('*%d* file(s) saved and queued for AI parsing.', $acceptedCount),
        ];

        if ($unsupportedDocuments->isNotEmpty()) {
            $lines[] = sprintf('*%d* file(s) not supported:', $unsupportedDocuments->count());
        }

        foreach ($unsupportedDocuments as $document) {
            $lines[] = '- '.self::rejectedDocumentSummary($document);
        }

        if ($failedDocuments->isNotEmpty()) {
            $lines[] = sprintf('*%d* file(s) could not be processed:', $failedDocuments->count());
        }

        foreach ($failedDocuments as $document) {
            $lines[] = '- '.self::rejectedDocumentSummary($document);
        }

        return self::compose(
            '📥',
            'Document received',
            implode("\n", $lines),
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private static function rejectedDocumentSummary(array $document): string
    {
        $filename = trim((string) ($document['filename'] ?? 'document.pdf'));
        $reason = (string) ($document['reason'] ?? 'pdf_unreadable');

        return match ($reason) {
            'pdf_page_limit' => sprintf(
                '%s - %d pages (maximum %d)',
                $filename,
                (int) ($document['page_count'] ?? 0),
                max(1, (int) config('services.documents.max_pdf_pages', 4)),
            ),
            'pdf_size_limit' => $filename.' - exceeds the PDF file-size limit',
            'pdf_password_protected' => $filename.' - password-protected PDFs are not supported',
            'pdf_processing_failed' => $filename.' - could not be processed; please resend the PDF',
            default => $filename.' - the PDF could not be read',
        };
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, currency?: string|null, payment_method?: string|null}  $details
     */
    public static function documentParsed(string $editUrl, array $details = []): string
    {
        return self::compose(
            '🎉',
            'Document parsed',
            self::documentDetailsBody($editUrl, $details),
        );
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, currency?: string|null, payment_method?: string|null}  $details
     */
    public static function documentNeedsReview(string $editUrl, array $details = []): string
    {
        return self::compose(
            '⚠️',
            'Document needs review',
            self::documentDetailsBody($editUrl, $details)."\n\nPlease review and confirm the details in the admin panel.",
        );
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, currency?: string|null, payment_method?: string|null}  $details
     */
    private static function documentDetailsBody(string $editUrl, array $details = []): string
    {
        $editUrl = trim($editUrl);
        $merchant = trim((string) ($details['merchant_name'] ?? ''));
        $paymentMethod = trim((string) ($details['payment_method'] ?? ''));

        if ($merchant === '') {
            $merchant = 'Unknown merchant';
        }

        if ($paymentMethod === '') {
            $paymentMethod = 'Unknown';
        }

        $totalAmount = array_key_exists('currency', $details)
            ? MoneyDisplay::withCurrency($details['total_amount'] ?? 0, $details['currency'])
            : MoneyDisplay::withPrefix($details['total_amount'] ?? 0);

        return implode("\n", [
            "Merchant: *{$merchant}*",
            "Total Amount: *{$totalAmount}*",
            "Payment Method: *{$paymentMethod}*",
            '',
            'Go to *expense edit*',
            $editUrl,
        ]);
    }

    public static function manualExpenseReceived(int $count): string
    {
        $count = max(1, $count);

        return self::compose(
            '📥',
            'Manual expense received',
            sprintf('A total of *%d* manual expense(s) saved and queued for AI parsing.', $count),
        );
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, payment_method?: string|null}  $details
     */
    public static function manualExpenseParsed(string $editUrl, array $details = []): string
    {
        $editUrl = trim($editUrl);
        $merchant = trim((string) ($details['merchant_name'] ?? ''));
        $paymentMethod = trim((string) ($details['payment_method'] ?? ''));

        if ($merchant === '') {
            $merchant = 'Unknown merchant';
        }

        if ($paymentMethod === '') {
            $paymentMethod = 'Unknown';
        }

        $totalAmount = MoneyDisplay::withPrefix($details['total_amount'] ?? 0);

        $body = implode("\n", [
            "Merchant: *{$merchant}*",
            "Total Amount: *{$totalAmount}*",
            "Payment Method: *{$paymentMethod}*",
            '',
            'Go to *expense edit*',
            $editUrl,
        ]);

        return self::compose('🎉', 'Manual expense parsed', $body);
    }

    /**
     * @param  list<array{title: string, amount: string, due_on: string, is_overdue: bool}>  $items
     */
    public static function recurringReminderSummary(string $indexUrl, array $items): string
    {
        $indexUrl = trim($indexUrl);
        $count = count($items);
        $overdueCount = 0;

        foreach ($items as $item) {
            if (($item['is_overdue'] ?? false) === true) {
                $overdueCount++;
            }
        }

        $summaryLine = match (true) {
            $count === 0 => 'No payments in your reminder window.',
            $overdueCount > 0 && $overdueCount === $count => sprintf(
                '*%d overdue payment%s*',
                $count,
                $count === 1 ? '' : 's',
            ),
            $overdueCount > 0 => sprintf(
                '*%d payment%s · %d overdue*',
                $count,
                $count === 1 ? '' : 's',
                $overdueCount,
            ),
            default => sprintf(
                '*%d payment%s in your reminder window*',
                $count,
                $count === 1 ? '' : 's',
            ),
        };

        $blocks = [$summaryLine];

        foreach ($items as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $amount = trim((string) ($item['amount'] ?? ''));
            $dueOn = trim((string) ($item['due_on'] ?? ''));
            $isOverdue = ($item['is_overdue'] ?? false) === true;

            if ($title === '') {
                $title = 'Untitled';
            }

            if ($amount === '') {
                $amount = 'variable';
            }

            $dueLabel = $isOverdue ? 'Overdue' : 'Due';

            $blocks[] = implode("\n", [
                "• *{$title}*",
                "  Amount: {$amount}",
                "  {$dueLabel}: {$dueOn}",
            ]);
        }

        if ($indexUrl !== '') {
            $blocks[] = "View recurrings\n{$indexUrl}";
        }

        $hasOverdue = $overdueCount > 0;

        return self::compose(
            $hasOverdue ? '⏰' : '📅',
            'Recurring payment summary',
            implode("\n\n", $blocks),
        );
    }
}
