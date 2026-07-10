<?php

declare(strict_types=1);

namespace App\Support;

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

    public static function receiptUploadFailed(int $attempt, int $maxAttempts = 3): string
    {
        $attempt = max(1, min($attempt, $maxAttempts));

        $title = sprintf('Receipt upload failed (attempt %d of %d)', $attempt, $maxAttempts);

        if ($attempt < $maxAttempts) {
            $body = "We could not download the receipt image from WhatsApp.\n\nWe will retry automatically in about 60 seconds.";
        } else {
            $body = "This was our final attempt and we still could not download the receipt image from WhatsApp.\n\nPlease send the photo again.";
        }

        return self::compose('❌', $title, $body);
    }
}
