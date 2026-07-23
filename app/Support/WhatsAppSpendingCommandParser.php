<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

final class WhatsAppSpendingCommandParser
{
    public const MODE_SUMMARY = 'summary';

    public const MODE_LABELS = 'labels';

    public const MODE_MERCHANTS = 'merchants';

    public const MODE_BUDGETS = 'budgets';

    public const MODE_TREND = 'trend';

    public const MODE_PAYMENT = 'payment';

    public const MODE_RECENT = 'recent';

    /**
     * @var array<string, list<string>>
     */
    private const MODE_KEYWORDS = [
        self::MODE_LABELS => ['labels', 'label', 'categories', 'category'],
        self::MODE_MERCHANTS => ['merchants', 'merchant', 'shops', 'shop'],
        self::MODE_BUDGETS => ['budgets', 'budget'],
        self::MODE_TREND => ['trend', 'history'],
        self::MODE_PAYMENT => ['payment', 'payments'],
        self::MODE_RECENT => ['recent', 'receipts'],
    ];

    /**
     * @var array<string, int>
     */
    private const MONTH_NAMES = [
        'january' => 1,
        'jan' => 1,
        'february' => 2,
        'feb' => 2,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'may' => 5,
        'june' => 6,
        'jun' => 6,
        'july' => 7,
        'jul' => 7,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'sept' => 9,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
    ];

    /**
     * @return array{mode: string, month: string}|null
     */
    public static function parse(string $text): ?array
    {
        $normalized = strtolower(trim($text));

        if (! str_contains($normalized, 'spend') && ! str_contains($normalized, 'total')) {
            return null;
        }

        $month = self::resolveMonth($normalized);
        $mode = self::resolveMode($normalized);

        return [
            'mode' => $mode,
            'month' => $month,
        ];
    }

    private static function resolveMonth(string $text): string
    {
        if (str_contains($text, 'last month')) {
            return now()->copy()->subMonth()->format('Y-m');
        }

        if (preg_match('#\b(20\d{2})[-/](\d{1,2})\b#', $text, $matches) === 1) {
            return sprintf('%s-%02d', $matches[1], (int) $matches[2]);
        }

        foreach (self::MONTH_NAMES as $name => $monthNumber) {
            if (! preg_match('/\b'.preg_quote($name, '/').'\b/', $text)) {
                continue;
            }

            $year = now()->year;

            if (preg_match('/\b(20\d{2})\b/', $text, $yearMatch) === 1) {
                $year = (int) $yearMatch[1];
            }

            $resolved = Carbon::createFromDate($year, $monthNumber, 1);

            if ($resolved->isFuture() && ! str_contains($text, (string) $year)) {
                $resolved->subYear();
            }

            return $resolved->format('Y-m');
        }

        return now()->format('Y-m');
    }

    private static function resolveMode(string $text): string
    {
        $withoutMonthPhrases = str_replace('last month', '', $text);

        foreach (self::MODE_KEYWORDS as $mode => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $withoutMonthPhrases) === 1) {
                    return $mode;
                }
            }
        }

        if (preg_match('/\blast\b/', $withoutMonthPhrases) === 1) {
            return self::MODE_RECENT;
        }

        return self::MODE_SUMMARY;
    }
}
