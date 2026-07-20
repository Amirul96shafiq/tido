<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PaymentMethod;
use App\Services\PaymentMethodMatcher;

final class ManualWhatsAppInvoiceParser
{
    /**
     * Item line: description, quantity, line_total;
     */
    private const ITEM_PATTERN = '/^(.+?),\s*(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)\s*;\s*$/u';

    /**
     * Merchant line: name; or name, payment_token; (must end with semicolon, not an item line)
     */
    private const MERCHANT_PATTERN = '/^(.+?)\s*;\s*$/u';

    public static function looksLike(string $text): bool
    {
        return self::parse($text) !== [];
    }

    /**
     * @return list<array{
     *     merchant_name: string,
     *     payment_method: PaymentMethod,
     *     items: list<array{description: string, quantity: float, line_total: float}>
     * }>
     */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        $blocks = [];
        /** @var array{merchant_name: string, payment_method: PaymentMethod}|null $currentMerchant */
        $currentMerchant = null;
        /** @var list<array{description: string, quantity: float, line_total: float}> $currentItems */
        $currentItems = [];
        $matcher = app(PaymentMethodMatcher::class);
        $tokenMap = $matcher->whatsappTokenMap();
        $defaultCash = $matcher->defaultCash();

        if ($defaultCash === null) {
            return [];
        }

        $flush = static function () use (&$blocks, &$currentMerchant, &$currentItems): void {
            if ($currentMerchant === null || $currentItems === []) {
                $currentMerchant = null;
                $currentItems = [];

                return;
            }

            $blocks[] = [
                'merchant_name' => $currentMerchant['merchant_name'],
                'payment_method' => $currentMerchant['payment_method'],
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
                $currentMerchant = self::parseMerchantLine($line, $tokenMap, $defaultCash);

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

    /**
     * @param  array<string, PaymentMethod>  $tokenMap
     * @return array{merchant_name: string, payment_method: PaymentMethod}
     */
    private static function parseMerchantLine(string $line, array $tokenMap, PaymentMethod $defaultCash): array
    {
        preg_match(self::MERCHANT_PATTERN, $line, $matches);
        $raw = trim($matches[1] ?? '');

        if (preg_match('/^(.+),\s*([^,]+)$/u', $raw, $parts) === 1) {
            $maybeToken = strtolower(trim($parts[2]));
            $maybeToken = str_replace([' ', '-', "'"], ['_', '_', ''], $maybeToken);
            $paymentMethod = $tokenMap[$maybeToken] ?? null;

            if ($paymentMethod !== null) {
                return [
                    'merchant_name' => trim($parts[1]),
                    'payment_method' => $paymentMethod,
                ];
            }
        }

        return [
            'merchant_name' => $raw,
            'payment_method' => $defaultCash,
        ];
    }
}
