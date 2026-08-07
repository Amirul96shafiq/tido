<?php

declare(strict_types=1);

namespace App\Prompts;

final class ReceiptCurrencyPrompt
{
    public static function build(): string
    {
        return <<<'PROMPT'
Inspect the receipt or invoice image only to identify the currency printed in the document.
You must respond with one raw JSON object only. Do not wrap it in markdown formatting.

Rules:
- Read the currency code, currency name, or symbol printed next to an amount.
- Never assume MYR because the receipt is being processed by a Malaysian expense app.
- Printed RM, MYR, Malaysian Ringgit, or Ringgit Malaysia means MYR.
- Printed USD, US$, US Dollar, or United States Dollar means USD.
- A bare $ means USD only when the document contains no competing dollar currency marker such as SGD, AUD, CAD, HKD, or NZD. Otherwise return null.
- Do not infer currency from the merchant's country, language, address, tax registration, payment method, or model knowledge.
- If the printed evidence is missing or genuinely ambiguous, return null.
- Do not read or return any monetary amount; this pass is only for currency detection.

The output JSON structure MUST match this exact schema:
{
  "currency": "String - three-letter ISO currency code printed or unambiguously represented in the document, or null",
  "evidence": "String - short exact printed currency code, name, or symbol that supports the result, or null"
}
PROMPT;
    }
}
