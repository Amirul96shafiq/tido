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

        $title = sprintf('Upload failed (attempt %d of %d)', $attempt, $maxAttempts);

        if ($attempt < $maxAttempts) {
            $body = "Download failed. The file could not be retrieved from WhatsApp.\n\nAutomatic retry in about 60 seconds.";
        } else {
            $body = "Download failed after the final attempt. The file could not be retrieved from WhatsApp.\n\nResend the document to try again.";
        }

        return self::compose('❌', $title, $body);
    }
}
