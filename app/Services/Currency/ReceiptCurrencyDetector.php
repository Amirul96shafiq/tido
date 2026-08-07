<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Invoice;
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
     * @return array{currency: ?string, source: string}
     */
    public function detect(
        ?string $documentText,
        array $base64Pages,
        ?string $fallbackCurrency = null,
    ): array {
        $documentCurrency = $this->detectFromText($documentText);

        if ($documentCurrency !== null) {
            return [
                'currency' => $documentCurrency,
                'source' => 'document_text',
            ];
        }

        $visionCurrencies = [];
        foreach ($base64Pages as $base64Page) {
            $visionResult = $this->ollama->generateJson(
                ReceiptCurrencyPrompt::build(),
                [$base64Page],
            );
            $visionCurrency = $this->normalizeCurrency($visionResult['currency'] ?? null);

            if ($visionCurrency !== null) {
                $visionCurrencies[$visionCurrency] = true;
            }
        }

        if (count($visionCurrencies) === 1) {
            return [
                'currency' => array_key_first($visionCurrencies),
                'source' => 'vision_currency_check',
            ];
        }

        if (count($visionCurrencies) > 1) {
            return [
                'currency' => null,
                'source' => 'conflicting_vision_evidence',
            ];
        }

        $fallback = $this->normalizeCurrency($fallbackCurrency);

        if ($fallback !== null && $fallback !== Invoice::CURRENCY_MYR) {
            return [
                'currency' => $fallback,
                'source' => 'receipt_extraction_fallback',
            ];
        }

        return [
            'currency' => null,
            'source' => 'undetermined',
        ];
    }

    public function detectFromText(?string $documentText): ?string
    {
        if (blank($documentText)) {
            return null;
        }

        $text = preg_replace('/\s+/u', ' ', strtoupper($documentText)) ?? strtoupper($documentText);
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
            return array_key_first($candidates);
        }

        if ($candidates !== []) {
            return null;
        }

        // A bare dollar sign is the common notation on foreign SaaS invoices. It is
        // treated as USD only when no country-specific dollar marker is present.
        if (preg_match('/\$\s*[-+]?[0-9]/', $text) === 1) {
            return 'USD';
        }

        return null;
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
}
