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
- Identify the source currency used for the receipt's line-item prices and primary total.
- Read the currency code, currency name, or symbol printed next to those source amounts.
- Ignore a secondary card-settlement, payment-history, or currency-conversion note when it
  shows a different currency from the source prices. For example, in
  "Charged RM25.44 using 1 USD = 4.2397 MYR", the receipt source currency is USD.
- Never assume MYR because the receipt is being processed by a Malaysian expense app.
- Printed RM, MYR, Malaysian Ringgit, or Ringgit Malaysia means MYR.
- Printed USD, US$, US Dollar, or United States Dollar means USD.
- A bare $ means USD only when the document contains no competing dollar currency marker such as SGD, AUD, CAD, HKD, or NZD. Otherwise return null.
- Do not infer currency from the merchant's country, language, address, tax registration, payment method, or model knowledge.
- If the printed evidence is missing or genuinely ambiguous, return null.
- If the receipt explicitly prints a source-currency-to-MYR rate such as "using 1 USD = 4.2397 MYR", return
  that numeric rate as MYR per one unit of the source currency. Return null when no such rate is printed or the
  rate is ambiguous. Never calculate or infer a rate from the receipt amounts or current exchange-rate knowledge.
- Do not read or return the receipt's primary monetary amounts; this pass is for currency and an explicitly printed rate only.

The output JSON structure MUST match this exact schema:
{
  "currency": "String - three-letter ISO currency code printed or unambiguously represented in the document, or null",
  "evidence": "String - short exact printed currency code, name, or symbol that supports the result, or null",
  "rate": "Number - explicitly printed MYR per one unit of the source currency, or null"
}
PROMPT;
    }

    public static function buildPrintedRate(): string
    {
        return <<<'PROMPT'
Read this receipt image specifically for a printed currency-conversion statement, usually near
Payment history or a Charged line. You must respond with one raw JSON object only. Do not wrap it
in markdown formatting.

Rules:
- Look for an explicit statement such as "using 1 USD = 4.2397 MYR" or an equivalent printed
  source-currency-to-MYR conversion statement.
- Return the source currency used for the receipt's line-item prices and primary total.
- Return the numeric MYR-per-source-unit rate only when that rate is visibly printed in the image.
- Never calculate or infer the rate from the receipt total, a settlement amount, current exchange
  rates, or the merchant's country.
- If no explicit conversion statement is visible, return null for the rate.

The output JSON structure MUST match this exact schema:
{
  "currency": "String - three-letter ISO source currency code, or null",
  "rate": "Number - explicitly printed MYR per one unit of the source currency, or null",
  "evidence": "String - short exact printed conversion statement, or null"
}
PROMPT;
    }
}
