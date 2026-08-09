<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Expense;
use App\Prompts\ReceiptCurrencyPrompt;
use App\Services\OllamaService;

final class ReceiptCurrencyDetector
{
    /**
     * @var array<string, list<string>>
     */
    private const DOCUMENT_PATTERNS = [
        'MYR' => [
            '/\bMYR\b/i',
            '/\bMALAYSIAN\s+RINGGIT\b/i',
            '/\bRINGGIT\s+MALAYSIA\b/i',
            '/(?<![A-Z])RM\s*(?=[0-9])/i',
        ],
        'USD' => [
            '/\bUSD\b/i',
            '/\bUS\s*\$/i',
            '/\bUS\s+DOLLARS?\b/i',
            '/\bUNITED\s+STATES\s+DOLLARS?\b/i',
        ],
        'SGD' => [
            '/\bSGD\b/i',
            '/\bSINGAPORE\s+DOLLARS?\b/i',
            '/(?<![A-Z])S\$\s*(?=[0-9])/i',
        ],
        'AUD' => [
            '/\bAUD\b/i',
            '/\bAUSTRALIAN\s+DOLLARS?\b/i',
            '/(?<![A-Z])A\$\s*(?=[0-9])/i',
        ],
        'CAD' => [
            '/\bCAD\b/i',
            '/\bCANADIAN\s+DOLLARS?\b/i',
            '/(?<![A-Z])C\$\s*(?=[0-9])/i',
        ],
        'HKD' => [
            '/\bHKD\b/i',
            '/\bHONG\s+KONG\s+DOLLARS?\b/i',
            '/(?<![A-Z])HK\$\s*(?=[0-9])/i',
        ],
        'NZD' => [
            '/\bNZD\b/i',
            '/\bNEW\s+ZEALAND\s+DOLLARS?\b/i',
            '/(?<![A-Z])NZ\$\s*(?=[0-9])/i',
        ],
        'EUR' => [
            '/\bEUR\b/i',
            '/\bEUROS?\b/i',
        ],
        'GBP' => [
            '/\bGBP\b/i',
        ],
        'JPY' => [
            '/\bJPY\b/i',
            '/\bJAPANESE\s+YEN\b/i',
        ],
        'CNY' => [
            '/\bCNY\b/i',
            '/\bRMB\b/i',
            '/\bYUAN\b/i',
        ],
        'THB' => [
            '/\bTHB\b/i',
            '/\bBAHT\b/i',
        ],
        'IDR' => [
            '/\bIDR\b/i',
            '/\bRUPIAH\b/i',
        ],
        'INR' => [
            '/\bINR\b/i',
        ],
        'KRW' => [
            '/\bKRW\b/i',
            '/\bKOREAN\s+WON\b/i',
        ],
        'PHP' => [
            '/\bPHP\b/i',
            '/\bPHILIPPINE\s+PESOS?\b/i',
        ],
        'VND' => [
            '/\bVND\b/i',
            '/\bVIETNAMESE\s+DONG\b/i',
        ],
        'CHF' => [
            '/\bCHF\b/i',
        ],
        'AED' => [
            '/\bAED\b/i',
        ],
        'SAR' => [
            '/\bSAR\b/i',
        ],
        'BRL' => [
            '/\bBRL\b/i',
        ],
        'ZAR' => [
            '/\bZAR\b/i',
        ],
    ];

    public function __construct(
        private readonly OllamaService $ollama,
    ) {}

    /**
     * @param  list<string>  $base64Pages
     * @return array{currency: ?string, source: string, rate?: float, rate_source?: string}
     */
    public function detect(
        ?string $documentText,
        array $base64Pages,
        ?string $fallbackCurrency = null,
    ): array {
        $fallback = $this->normalizeCurrency($fallbackCurrency);
        $documentDetails = $this->detectDocumentDetails($documentText);
        $documentCurrency = $documentDetails['currency'];

        if ($documentCurrency !== null) {
            return $this->buildDetection(
                $documentCurrency,
                'document_text',
                $documentDetails['rate'],
            );
        }

        /** @var list<array{currency: ?string, source_currency: ?string, rate: ?float}> $visionResults */
        $visionResults = [];
        $visionCurrencies = [];
        foreach ($base64Pages as $base64Page) {
            $visionResult = $this->ollama->generateJson(
                ReceiptCurrencyPrompt::build(),
                [$base64Page],
            );
            $visionCurrency = is_array($visionResult)
                ? $this->normalizeCurrency($visionResult['currency'] ?? null)
                : null;
            $visionRate = is_array($visionResult)
                ? $this->normalizeRate($visionResult['rate'] ?? null)
                : null;
            $visionSourceCurrency = $visionCurrency;

            $evidence = is_array($visionResult)
                ? ($visionResult['rate_evidence'] ?? $visionResult['evidence'] ?? null)
                : null;

            if (is_string($evidence) && trim($evidence) !== '') {
                $evidenceText = preg_replace('/\s+/u', ' ', strtoupper($evidence)) ?? strtoupper($evidence);
                $evidenceDetails = $this->detectConversionEvidence($evidenceText);

                if ($evidenceDetails !== null && $evidenceDetails['currency'] !== null) {
                    $visionSourceCurrency = $evidenceDetails['currency'];
                    $visionRate ??= $evidenceDetails['rate'];
                }
            }

            if ($visionSourceCurrency !== null
                && $visionSourceCurrency !== Expense::CURRENCY_MYR
                && $visionRate === null) {
                $printedRate = $this->detectPrintedRateFromVision($base64Page, $visionSourceCurrency);
                $visionRate = $printedRate['rate'];
                $visionSourceCurrency = $printedRate['currency'] ?? $visionSourceCurrency;
            }

            $visionResults[] = [
                'currency' => $visionCurrency,
                'source_currency' => $visionSourceCurrency,
                'rate' => $visionRate,
            ];

            if ($visionCurrency !== null) {
                $visionCurrencies[$visionCurrency] = true;
            }
        }

        if (count($visionCurrencies) === 1) {
            $visionCurrency = array_key_first($visionCurrencies);

            if ($visionCurrency === Expense::CURRENCY_MYR
                && $fallback !== null
                && $fallback !== Expense::CURRENCY_MYR) {
                return $this->buildDetection(
                    $fallback,
                    'receipt_extraction_fallback',
                    $this->consistentVisionRate($visionResults, $fallback, 'source_currency'),
                );
            }

            return $this->buildDetection(
                $visionCurrency,
                'vision_currency_check',
                $this->consistentVisionRate($visionResults, $visionCurrency, 'source_currency'),
            );
        }

        if (count($visionCurrencies) > 1) {
            $foreignCurrencies = array_values(array_filter(
                array_keys($visionCurrencies),
                static fn (string $currency): bool => $currency !== Expense::CURRENCY_MYR,
            ));

            if ($fallback !== null
                && $fallback !== Expense::CURRENCY_MYR
                && count($foreignCurrencies) === 1
                && $foreignCurrencies[0] === $fallback) {
                return $this->buildDetection(
                    $fallback,
                    'receipt_extraction_fallback',
                    $this->consistentVisionRate($visionResults, $fallback, 'source_currency'),
                );
            }

            return $this->buildDetection(null, 'conflicting_vision_evidence');
        }

        if ($fallback !== null && $fallback !== Expense::CURRENCY_MYR) {
            return $this->buildDetection($fallback, 'receipt_extraction_fallback');
        }

        return $this->buildDetection(null, 'undetermined');
    }

    /**
     * @return array{currency: ?string, rate: ?float}
     */
    private function detectPrintedRateFromVision(string $base64Page, string $sourceCurrency): array
    {
        $rateResult = $this->ollama->generateJson(
            ReceiptCurrencyPrompt::buildPrintedRate(),
            [$base64Page],
        );

        if (! is_array($rateResult)) {
            return ['currency' => $sourceCurrency, 'rate' => null];
        }

        $reportedCurrency = $this->normalizeCurrency($rateResult['currency'] ?? null);
        $rate = $this->normalizeRate($rateResult['rate'] ?? null);
        $evidence = $rateResult['rate_evidence'] ?? $rateResult['evidence'] ?? null;
        $evidenceCurrency = null;

        if (is_string($evidence) && trim($evidence) !== '') {
            $evidenceText = preg_replace('/\s+/u', ' ', strtoupper($evidence)) ?? strtoupper($evidence);
            $evidenceDetails = $this->detectConversionEvidence($evidenceText);

            if ($evidenceDetails !== null) {
                $evidenceCurrency = $evidenceDetails['currency'];
                $rate ??= $evidenceDetails['rate'];
            }
        }

        if (($reportedCurrency !== null && $reportedCurrency !== $sourceCurrency)
            || ($evidenceCurrency !== null && $evidenceCurrency !== $sourceCurrency)) {
            return ['currency' => $sourceCurrency, 'rate' => null];
        }

        return [
            'currency' => $reportedCurrency ?? $evidenceCurrency ?? $sourceCurrency,
            'rate' => $rate,
        ];
    }

    public function detectFromText(?string $documentText): ?string
    {
        return $this->detectDocumentDetails($documentText)['currency'];
    }

    /**
     * @return array{currency: ?string, rate: ?float}
     */
    private function detectDocumentDetails(?string $documentText): array
    {
        if (blank($documentText)) {
            return ['currency' => null, 'rate' => null];
        }

        $text = preg_replace('/\s+/u', ' ', strtoupper($documentText)) ?? strtoupper($documentText);
        $conversionEvidence = $this->detectConversionEvidence($text);

        if ($conversionEvidence !== null) {
            return $conversionEvidence;
        }

        $candidates = [];

        foreach (self::DOCUMENT_PATTERNS as $currency => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $candidates[$currency] = true;
                    break;
                }
            }
        }

        if (count($candidates) === 1) {
            return [
                'currency' => array_key_first($candidates),
                'rate' => null,
            ];
        }

        if ($candidates !== []) {
            return ['currency' => null, 'rate' => null];
        }

        // A bare dollar sign is the common notation on foreign SaaS expenses. It is
        // treated as USD only when no country-specific dollar marker is present.
        return [
            'currency' => preg_match('/\$\s*[-+]?[0-9]/', $text) === 1 ? 'USD' : null,
            'rate' => null,
        ];
    }

    /**
     * @return array{currency: ?string, rate: ?float}|null
     */
    private function detectConversionEvidence(string $text): ?array
    {
        if (preg_match(
            '/\bUSING\s+1\s+(USD|MYR|SGD|AUD|CAD|HKD|NZD|EUR|GBP|JPY|CNY|THB|IDR|INR|KRW|PHP|VND|CHF|AED|SAR|BRL|ZAR|US\$|S\$|A\$|C\$|HK\$|NZ\$|\$)\s*=\s*([-+]?[0-9][0-9,.]*)\s+(MYR|RM|[A-Z]{3})\b/i',
            $text,
            $matches,
        ) !== 1) {
            return null;
        }

        $sourceCurrency = $this->normalizeCurrencyMarker($matches[1]);
        $targetCurrency = $this->normalizeCurrencyMarker($matches[3]);

        return [
            'currency' => $sourceCurrency,
            'rate' => $targetCurrency === Expense::CURRENCY_MYR
                ? $this->normalizeRate($matches[2])
                : null,
        ];
    }

    /**
     * @return array{currency: ?string, source: string, rate?: float, rate_source?: string}
     */
    private function buildDetection(?string $currency, string $source, ?float $rate = null): array
    {
        $detection = [
            'currency' => $currency,
            'source' => $source,
        ];

        if ($rate !== null && $currency !== null && $currency !== Expense::CURRENCY_MYR) {
            $detection['rate'] = $rate;
            $detection['rate_source'] = 'printed_receipt_rate';
        }

        return $detection;
    }

    /**
     * @param  list<array{currency: ?string, source_currency: ?string, rate: ?float}>  $visionResults
     */
    private function consistentVisionRate(
        array $visionResults,
        string $currency,
        string $currencyKey = 'currency',
    ): ?float {
        $rates = [];

        foreach ($visionResults as $visionResult) {
            if ($visionResult[$currencyKey] === $currency && $visionResult['rate'] !== null) {
                $rates[] = $visionResult['rate'];
            }
        }

        if ($rates === []) {
            return null;
        }

        $firstRate = $rates[0];
        foreach ($rates as $rate) {
            if (abs($rate - $firstRate) > 0.00000001) {
                return null;
            }
        }

        return $firstRate;
    }

    private function normalizeCurrencyMarker(string $marker): ?string
    {
        $marker = strtoupper(trim($marker));

        return match ($marker) {
            '$', 'US$' => 'USD',
            'RM' => Expense::CURRENCY_MYR,
            'S$' => 'SGD',
            'A$' => 'AUD',
            'C$' => 'CAD',
            'HK$' => 'HKD',
            'NZ$' => 'NZD',
            default => $this->normalizeCurrency($marker),
        };
    }

    private function normalizeCurrency(mixed $currency): ?string
    {
        if (! is_string($currency)) {
            return null;
        }

        $normalized = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $normalized) === 1
            ? $normalized
            : null;
    }

    private function normalizeRate(mixed $rate): ?float
    {
        if (is_string($rate)) {
            $rate = str_replace(',', '', trim($rate));
        }

        if (! is_numeric($rate)) {
            return null;
        }

        $normalized = (float) $rate;

        return is_finite($normalized) && $normalized > 0
            ? round($normalized, 10)
            : null;
    }
}
