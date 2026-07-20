<?php

declare(strict_types=1);

namespace App\Support;

final class ManualWhatsAppInvoiceParser
{
    /**
     * Item line: description, quantity, line_total;
     */
    private const ITEM_PATTERN = '/^(.+?),\s*(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)\s*;\s*$/u';

    /**
     * Merchant line: name; (must end with semicolon, not an item line)
     */
    private const MERCHANT_PATTERN = '/^(.+?)\s*;\s*$/u';

    public static function looksLike(string $text): bool
    {
        return self::parse($text) !== [];
    }

    /**
     * @return list<array{
     *     merchant_name: string,
     *     items: list<array{description: string, quantity: float, line_total: float}>
     * }>
     */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        $blocks = [];
        $currentMerchant = null;
        /** @var list<array{description: string, quantity: float, line_total: float}> $currentItems */
        $currentItems = [];

        $flush = static function () use (&$blocks, &$currentMerchant, &$currentItems): void {
            if ($currentMerchant === null || $currentItems === []) {
                $currentMerchant = null;
                $currentItems = [];

                return;
            }

            $blocks[] = [
                'merchant_name' => $currentMerchant,
                'items' => $currentItems,
            ];
            $currentMerchant = null;
            $currentItems = [];
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $flush();

                continue;
            }

            if (self::isItemLine($line)) {
                if ($currentMerchant === null) {
                    return [];
                }

                $item = self::parseItemLine($line);
                if ($item === null) {
                    return [];
                }

                $currentItems[] = $item;

                continue;
            }

            if (self::isMerchantLine($line)) {
                $flush();
                $currentMerchant = self::parseMerchantLine($line);

                continue;
            }

            return [];
        }

        $flush();

        return $blocks;
    }

    private static function isItemLine(string $line): bool
    {
        return preg_match(self::ITEM_PATTERN, $line) === 1;
    }

    private static function isMerchantLine(string $line): bool
    {
        if (self::isItemLine($line)) {
            return false;
        }

        return preg_match(self::MERCHANT_PATTERN, $line) === 1;
    }

    /**
     * @return array{description: string, quantity: float, line_total: float}|null
     */
    private static function parseItemLine(string $line): ?array
    {
        if (preg_match(self::ITEM_PATTERN, $line, $matches) !== 1) {
            return null;
        }

        $description = trim($matches[1]);
        if ($description === '') {
            return null;
        }

        return [
            'description' => $description,
            'quantity' => (float) $matches[2],
            'line_total' => (float) $matches[3],
        ];
    }

    private static function parseMerchantLine(string $line): string
    {
        preg_match(self::MERCHANT_PATTERN, $line, $matches);

        return trim($matches[1] ?? '');
    }
}
