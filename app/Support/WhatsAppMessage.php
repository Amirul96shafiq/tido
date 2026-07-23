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
                'Use one of the approaches below in WhatsApp chat to start AI parsing and store it as an invoice:',
                '- Upload a *document(s)* or *image(s)*',
                '- Type a *manual invoice text* (type "*manual way*" to learn more)',
                '- Type *spend* or *total* to view the current month\'s expenses',
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
            '[Invoice title], [Payment method];',
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

    public static function documentReceived(int $count): string
    {
        $count = max(1, $count);

        return self::compose(
            '📥',
            'Document received',
            sprintf('A total of *%d* file(s) saved and queued for AI parsing.', $count),
        );
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, payment_method?: string|null}  $details
     */
    public static function documentParsed(string $editUrl, array $details = []): string
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
            'Go to *invoice edit*',
            $editUrl,
        ]);

        return self::compose('🎉', 'Document parsed', $body);
    }

    public static function manualInvoiceReceived(int $count): string
    {
        $count = max(1, $count);

        return self::compose(
            '📥',
            'Manual invoice received',
            sprintf('A total of *%d* manual invoice(s) saved and queued for AI parsing.', $count),
        );
    }

    /**
     * @param  array{merchant_name?: string|null, total_amount?: float|int|string|null, payment_method?: string|null}  $details
     */
    public static function manualInvoiceParsed(string $editUrl, array $details = []): string
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
            'Go to *invoice edit*',
            $editUrl,
        ]);

        return self::compose('🎉', 'Manual invoice parsed', $body);
    }
}
