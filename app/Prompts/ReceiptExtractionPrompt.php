<?php

declare(strict_types=1);

namespace App\Prompts;

use App\Models\Label;
use App\Models\PaymentMethod;

class ReceiptExtractionPrompt
{
    public static function build(): string
    {
        $labelLines = Label::financeLabels()
            ->map(function (Label $label): string {
                $hintText = self::plainTextHint($label->description);
                $hint = $hintText !== ''
                    ? ' — '.$hintText
                    : '';

                return '- '.$label->name.$hint;
            })
            ->implode("\n");

        $paymentMethods = PaymentMethod::orderedForSelect();
        $paymentMethodNames = $paymentMethods
            ->pluck('name')
            ->implode(', ');
        $paymentMethodLines = $paymentMethods
            ->map(function (PaymentMethod $method): string {
                $parts = [];

                $aliases = collect($method->aliases ?? [])
                    ->filter(fn (mixed $alias): bool => is_string($alias) && $alias !== '')
                    ->values();

                if ($aliases->isNotEmpty()) {
                    $parts[] = 'aliases: '.$aliases->implode(', ');
                }

                $notesHint = self::plainTextHint($method->notes);
                if ($notesHint !== '') {
                    $parts[] = $notesHint;
                }

                $hint = $parts !== []
                    ? ' — '.implode('; ', $parts)
                    : '';

                return '- '.$method->name.$hint;
            })
            ->implode("\n");

        if ($paymentMethodLines === '') {
            $paymentMethodLines = '- (none configured)';
            $paymentMethodNames = 'null';
        }

        $labelClassificationRules = LabelClassificationRules::promptLines();

        return <<<PROMPT
Please inspect this document image and extract financial information only when it contains a genuine receipt, invoice, or bill.
You must respond with a raw JSON object only. Do not wrap it in markdown formatting (like ```json).

Document classification rules (follow strictly):
- Set document_classification to "receipt" when the image contains a genuine purchase receipt, invoice, bill, utility bill, or payment receipt / online payment confirmation with transaction information (including myTNB, FPX, bank, or e-wallet payment success pages).
- Payment receipts that show a reference number, account, amount paid, and payment method are receipts even when they are not store checkout slips.
- Set document_classification to "not_receipt" for photos, screenshots, menus, identity documents, forms, blank pages, unrelated documents, or images without receipt or payment information.
- Never invent merchant, invoice, date, amount, currency, payment, or line-item data to make a document look like a receipt.
- When document_classification is "not_receipt", return null for merchant_name, invoice_number, date_time, currency, and payment_method; return 0 for money fields; and return an empty items array.

Malaysia receipt rules (follow strictly):
- Dates are usually DD/MM/YY or DD/MM/YYYY (day first). If the receipt shows 14/07/26 or 14/07/2026, output 2026-07-14 (day first; two-digit years use 2000+).
- Read the printed Date / Time line carefully. Do not invent the day from other numbers (expense no, terminal id, batch, approval). Example: if Date is 08/07/2026, date_time must be 2026-07-08 … not 2026-07-14.
- date_time MUST be exactly "YYYY-MM-DD HH:MM:SS" with a space separator. Never use T, Z, milliseconds, or timezone suffixes.
- Never invent a year from the day number (e.g. day 14 must NOT become year 2014). Prefer a printed 4-digit year when visible (e.g. 14-Jul-2026).
- invoice_number is the receipt / bill / invoice reference only (e.g. Bill No, Invoice No). Never use company registration numbers (e.g. 199401020616) or tax IDs (CBP / SST / TIN) as invoice_number.
- merchant_name should be the store brand plus branch when visible (e.g. myNEWS Bayu Residensi, TMG Mart Sri Gombak).
- For each line item, extract serial_number when a barcode / SKU / PLU / item code is printed under or beside the description (digits only strings like 9556072080026). Use null if none is printed.
- For weight or unit-priced lines, compute line_total as quantity × unit_price (e.g. 5 × 0.220 = 1.10).
- All money fields must be JSON numbers (or 0). Never use strings like "None", "null", or blank. Never nest money as objects.
- Never default the currency to MYR because the app is Malaysian, the merchant is Malaysian, or the receipt has no visible currency marker.
- Detect currency from a printed ISO code or unambiguous currency marker on the receipt. Use MYR for printed RM, MYR, or Malaysian Ringgit evidence.
- Use USD for printed USD, US$, US Dollar, or a bare $ when no competing dollar-currency marker is visible; use null when the dollar symbol is genuinely ambiguous.
- When a receipt includes a separate card-settlement or currency-conversion note, use the currency of the line-item prices and primary receipt total as the source currency. For example, "Charged RM25.44 using 1 USD = 4.2397 MYR" means the source receipt amount is USD; do not replace it with the settlement currency MYR.
- Keep every money value in the detected source currency. Do not convert amounts yourself.
- Prefer Grand Total / Total Paid / Amount Paid for total_amount over guessing from partial lines.
- payment_method must be an exact name from the available payment methods list below, or null. Prefer aliases when the receipt wording matches them.

Line item label rules (follow strictly):
- Every item in items[] MUST include a label.
- Classify each line by its description, not the merchant alone (a grocery receipt can mix Groceries & Household and Food & Dining lines).
- Use the exact label name from the list below. Pick the closest match when ambiguous.
- Ready-to-eat / convenience-store snacks and drinks → Food & Dining. Supermarket pantry, fresh produce, and household consumables → Groceries & Household.
- Packaged bread loaves (e.g. Gardenia Original Classic Bread) → Groceries & Household. Gardenia Quick Bites / Puazz and similar ready-to-eat snacks → Food & Dining.
{$labelClassificationRules}

Available labels (use exact name in each item's "label" field):
{$labelLines}

Available payment methods (use exact name in "payment_method"):
{$paymentMethodLines}

The output JSON structure MUST match this exact schema:
{
  "document_classification": "String - exactly receipt or not_receipt",
  "merchant_name": "String or null - prefer store brand and branch (e.g. FamilyMart Pinggiran Batu Caves)",
  "invoice_number": "String or null - invoice or receipt reference number",
  "date_time": "String or null - YYYY-MM-DD HH:MM:SS only (example: 2026-07-14 20:56:20)",
  "subtotal": "Number - pre-tax / pre-rounding merchandise total",
  "total_tax": "Number - total SST / service tax / GST (include service charge if not split)",
  "discount_total": "Number - total discounts and savings (0 if none)",
  "rounding_amount": "Number - rounding adjustment, may be negative (0 if none)",
  "total_amount": "Number - final total paid amount",
  "currency": "String - three-letter ISO currency code from printed evidence, or null when missing/ambiguous",
  "payment_method": "String or null - exact payment method name from the list above (e.g. {$paymentMethodNames})",
  "items": [
    {
      "description": "String - line item title",
      "quantity": "Number - unit quantity (supports decimals for kg / litres)",
      "unit_price": "Number - cost per single unit",
      "line_total": "Number - total price for this line after line discounts",
      "serial_number": "String or null - barcode / SKU / PLU / item code printed for this line",
      "label": "String - exact label name from the list above"
    }
  ]
}
PROMPT;
    }

    /** @deprecated Use build() instead */
    public static function get(): string
    {
        return self::build();
    }

    /**
     * Strip rich-editor HTML (and collapse whitespace) for prompt injection.
     */
    private static function plainTextHint(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return '';
        }

        $plain = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }
}
