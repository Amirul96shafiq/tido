<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ReceiptParseNormalizer
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *     merchant_name: string,
     *     invoice_number: ?string,
     *     date_time: ?Carbon,
     *     subtotal: float,
     *     total_tax: float,
     *     discount_total: float,
     *     rounding_amount: float,
     *     total_amount: float,
     *     currency: string,
     *     payment_method: mixed,
     *     items: list<array{description: string, quantity: float, unit_price: float, line_total: float, serial_number: ?string, label: ?string}>
     * }
     */
    public function normalize(array $parsed): array
    {
        $merchantName = trim((string) ($parsed['merchant_name'] ?? ''));
        if ($merchantName === '') {
            $merchantName = 'Unknown Merchant';
        }

        $invoiceNumber = $parsed['invoice_number'] ?? null;
        if (is_string($invoiceNumber)) {
            $invoiceNumber = trim($invoiceNumber);
            if ($invoiceNumber === '' || $this->looksLikeCompanyRegistration($invoiceNumber)) {
                $invoiceNumber = null;
            }
        } else {
            $invoiceNumber = null;
        }

        $items = [];
        if (! empty($parsed['items']) && is_array($parsed['items'])) {
            foreach ($parsed['items'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $description = trim((string) ($item['description'] ?? ''));
                $quantity = $this->toQuantity($item['quantity'] ?? 1);
                $unitPrice = $this->toMoney($item['unit_price'] ?? 0);
                $lineTotal = $this->toMoney($item['line_total'] ?? 0);

                if ($description === '' && $unitPrice === 0.0 && $lineTotal === 0.0) {
                    continue;
                }

                if ($description === '') {
                    $description = 'Line Item';
                }

                $labelName = $item['label'] ?? $item['suggested_category'] ?? null;
                if (is_string($labelName)) {
                    $labelName = trim($labelName);
                    if ($labelName === '') {
                        $labelName = null;
                    }
                } else {
                    $labelName = null;
                }

                $items[] = [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'serial_number' => $this->toSerialNumber(
                        $item['serial_number'] ?? $item['barcode'] ?? $item['sku'] ?? null
                    ),
                    'label' => $labelName,
                ];
            }
        }

        return [
            'merchant_name' => $merchantName,
            'invoice_number' => $invoiceNumber,
            'date_time' => $this->parseDateTime($parsed['date_time'] ?? null),
            'subtotal' => $this->toMoney($parsed['subtotal'] ?? 0),
            'total_tax' => $this->toMoney($parsed['total_tax'] ?? 0),
            'discount_total' => $this->toMoney($parsed['discount_total'] ?? 0),
            'rounding_amount' => $this->toMoney($parsed['rounding_amount'] ?? 0),
            'total_amount' => $this->toMoney($parsed['total_amount'] ?? 0),
            'currency' => filled($parsed['currency'] ?? null)
                ? strtoupper(substr(trim((string) $parsed['currency']), 0, 3))
                : 'MYR',
            'payment_method' => $parsed['payment_method'] ?? null,
            'items' => $items,
        ];
    }

    public function toMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_array($value) && array_key_exists('value', $value)) {
            return $this->toMoney($value['value']);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || Str::lower($trimmed) === 'none' || Str::lower($trimmed) === 'null') {
                return 0.0;
            }

            $trimmed = str_replace([',', 'RM', 'MYR', ' '], '', $trimmed);
            if (! is_numeric($trimmed)) {
                return 0.0;
            }

            return round((float) $trimmed, 2);
        }

        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    public function toQuantity(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 1.0;
        }

        if (is_array($value) && array_key_exists('value', $value)) {
            return $this->toQuantity($value['value']);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || Str::lower($trimmed) === 'none' || Str::lower($trimmed) === 'null') {
                return 1.0;
            }

            if (! is_numeric($trimmed)) {
                return 1.0;
            }

            return round((float) $trimmed, 3);
        }

        if (! is_numeric($value)) {
            return 1.0;
        }

        $quantity = round((float) $value, 3);

        return $quantity > 0 ? $quantity : 1.0;
    }

    public function toSerialNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $serial = trim((string) $value);
        if ($serial === '' || Str::lower($serial) === 'none' || Str::lower($serial) === 'null') {
            return null;
        }

        return $serial;
    }

    /**
     * Parse AI datetime strings with Malaysia day-first preference.
     * Returns null when the value cannot be parsed reliably (never falls back to now()).
     */
    public function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = $this->sanitizeDateTimeString($raw);
        if ($raw === '') {
            return null;
        }

        // Prefer Malaysia day-first formats; try 2-digit years before 4-digit Y.
        // Trailing | resets omitted fields (e.g. seconds) to Unix epoch defaults.
        $dayFirstTwoDigitYearFormats = [
            'd/m/y H:i:s',
            'd/m/y H:i|',
            'd/m/y|',
            'd/n/y H:i:s',
            'd/n/y H:i|',
            'd/n/y|',
            'd-m-y H:i:s',
            'd-m-y H:i|',
            'd-m-y|',
            'd-n-y H:i:s',
            'd-n-y H:i|',
            'd-n-y|',
        ];

        $dayFirstFourDigitYearFormats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i|',
            'd/m/Y|',
            'd-m-Y H:i:s',
            'd-m-Y H:i|',
            'd-m-Y|',
            'd-M-Y H:i:s',
            'd-M-Y H:i|',
            'd-M-Y|',
            'j-M-Y H:i:s',
            'j-M-Y H:i|',
            'j-M-Y|',
        ];

        $yearFirstFormats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i|',
            'Y-m-d|',
            'Y/m/d H:i:s',
            'Y/m/d H:i|',
            'Y/m/d|',
            'Y/n/j H:i:s',
            'Y/n/j H:i|',
            'Y/n/j g:iA|',
            'Y/n/j g:i A|',
            'Y/m/d g:iA|',
            'Y/m/d g:i A|',
        ];

        $hasFourDigitYear = preg_match('/\b\d{4}\b/', $raw) === 1;
        $hasTwoDigitYearOnly = ! $hasFourDigitYear && preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2}\b/', $raw) === 1;

        if ($hasTwoDigitYearOnly) {
            foreach ($dayFirstTwoDigitYearFormats as $format) {
                $parsed = $this->tryCreateFromFormat($format, $raw);
                if ($parsed !== null) {
                    return $this->applyTwoDigitYearWindow($parsed, $raw);
                }
            }

            // Ambiguous numeric day-first dates must not fall through to Carbon::parse (US m/d/y).
            return null;
        }

        if ($hasFourDigitYear) {
            foreach ($dayFirstFourDigitYearFormats as $format) {
                $parsed = $this->tryCreateFromFormat($format, $raw);
                if ($parsed !== null && $parsed->year >= 1000) {
                    return $parsed;
                }
            }
        }

        // Only attempt year-first formats when the string starts with a 4-digit year.
        if (preg_match('/^\d{4}\b/', $raw) === 1) {
            foreach ($yearFirstFormats as $format) {
                $parsed = $this->tryCreateFromFormat($format, $raw);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * Reject implausible receipt dates (hallucinated years, far-future values).
     */
    public function isDateTimeSane(?Carbon $dateTime, ?Carbon $reference = null): bool
    {
        if ($dateTime === null) {
            return false;
        }

        $reference ??= Carbon::now();
        $minYear = $reference->year - 1;
        $latestAllowed = $reference->copy()->addDay()->endOfDay();

        if ($dateTime->year < $minYear) {
            return false;
        }

        if ($dateTime->greaterThan($latestAllowed)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{subtotal: float, total_tax: float, discount_total: float, rounding_amount: float, total_amount: float, items: list<array{line_total: float}>}  $normalized
     */
    public function amountsReconcile(array $normalized): bool
    {
        $headerTotal = $normalized['subtotal']
            + $normalized['total_tax']
            - $normalized['discount_total']
            + $normalized['rounding_amount'];

        if (abs($headerTotal - $normalized['total_amount']) > 0.10) {
            return false;
        }

        if (count($normalized['items']) === 0) {
            return true;
        }

        $itemsSum = 0.0;
        foreach ($normalized['items'] as $item) {
            $itemsSum += $item['line_total'];
        }

        $matchesSubtotal = abs($itemsSum - $normalized['subtotal']) <= 0.05;
        $matchesTotal = abs($itemsSum - $normalized['total_amount']) <= 0.05;

        return $matchesSubtotal || $matchesTotal;
    }

    private function sanitizeDateTimeString(string $raw): string
    {
        $sanitized = trim($raw);

        // Drop trailing Z / numeric timezone offsets.
        $sanitized = preg_replace('/Z$/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/[+-]\d{2}:?\d{2}$/', '', $sanitized) ?? $sanitized;

        // ISO-ish T separator → space (handles "14/07/26T", "06/07/26T18:36:11.000").
        $sanitized = preg_replace('/T/i', ' ', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized)) ?? $sanitized;

        // Fractional seconds: 18:36:11.000 → 18:36:11
        $sanitized = preg_replace('/(\d{1,2}:\d{2}:\d{2})\.\d+/', '$1', $sanitized) ?? $sanitized;

        // OCR glitch: 21:05.38 → 21:05:38
        $sanitized = preg_replace('/(\d{1,2}:\d{2})\.(\d{2})\b/', '$1:$2', $sanitized) ?? $sanitized;

        // Normalize "3:15PM" → "3:15 PM" for g:i A formats.
        $sanitized = preg_replace('/(\d{1,2}:\d{2})\s*(AM|PM)\b/i', '$1 $2', $sanitized) ?? $sanitized;

        return trim($sanitized);
    }

    private function tryCreateFromFormat(string $format, string $raw): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat($format, $raw, 'Asia/Kuala_Lumpur');
            if ($parsed === false) {
                return null;
            }

            // Reject overflows (e.g. format mismatch leaving unexpected leftovers).
            $errors = Carbon::getLastErrors();
            if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
                return null;
            }

            return $parsed;
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyTwoDigitYearWindow(Carbon $parsed, string $raw): Carbon
    {
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})\b/', $raw, $dateMatches) !== 1) {
            return $parsed;
        }

        $twoDigitYear = (int) $dateMatches[3];
        $fullYear = $twoDigitYear <= 69 ? 2000 + $twoDigitYear : 1900 + $twoDigitYear;
        $parsed->year($fullYear);

        return $parsed;
    }

    private function looksLikeCompanyRegistration(string $value): bool
    {
        // e.g. "199401020616 (306295-X CBP 000709361664"
        if (preg_match('/\bCBP\b/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^\d{10,}\s*\(/', $value) === 1) {
            return true;
        }

        return false;
    }
}
