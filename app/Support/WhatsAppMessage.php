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
}
