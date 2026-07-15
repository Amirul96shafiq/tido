<?php

declare(strict_types=1);

namespace App\Prompts;

class ReceiptExtractionPrompt
{
    public static function get(): string
    {
        return <<<'PROMPT'
Please extract financial information from this receipt image.
You must respond with a raw JSON object only. Do not wrap it in markdown formatting (like ```json).

Malaysia receipt rules (follow strictly):
- Dates are usually DD/MM/YY or DD/MM/YYYY (day first). If the receipt shows 14/07/26 or 14/07/2026, output 2026-07-14 (day first; two-digit years use 2000+).
- Read the printed Date / Time line carefully. Do not invent the day from other numbers (invoice no, terminal id, batch, approval). Example: if Date is 08/07/2026, date_time must be 2026-07-08 … not 2026-07-14.
- date_time MUST be exactly "YYYY-MM-DD HH:MM:SS" with a space separator. Never use T, Z, milliseconds, or timezone suffixes.
- Never invent a year from the day number (e.g. day 14 must NOT become year 2014). Prefer a printed 4-digit year when visible (e.g. 14-Jul-2026).
- invoice_number is the receipt / bill / invoice reference only (e.g. Bill No, Invoice No). Never use company registration numbers (e.g. 199401020616) or tax IDs (CBP / SST / TIN) as invoice_number.
- merchant_name should be the store brand plus branch when visible (e.g. myNEWS Bayu Residensi, TMG Mart Sri Gombak).
- For each line item, extract serial_number when a barcode / SKU / PLU / item code is printed under or beside the description (digits only strings like 9556072080026). Use null if none is printed.
- For weight or unit-priced lines, compute line_total as quantity × unit_price (e.g. 5 × 0.220 = 1.10).
- All money fields must be JSON numbers (or 0). Never use strings like "None", "null", or blank. Never nest money as objects.
- Prefer Grand Total / Total Paid / Amount Paid for total_amount over guessing from partial lines.
- payment_method must be one of: mastercard, visa, mykasih, cash, pay_with_qr, touchngo, other, or null. Map DEBIT / CREDIT / debit card / credit card to other.

The output JSON structure MUST match this exact schema:
{
  "merchant_name": "String - prefer store brand and branch (e.g. FamilyMart Pinggiran Batu Caves)",
  "invoice_number": "String or null - invoice or receipt reference number",
  "date_time": "String - YYYY-MM-DD HH:MM:SS only (example: 2026-07-14 20:56:20)",
  "subtotal": "Number - pre-tax / pre-rounding merchandise total",
  "total_tax": "Number - total SST / service tax / GST (include service charge if not split)",
  "discount_total": "Number - total discounts and savings (0 if none)",
  "rounding_amount": "Number - rounding adjustment, may be negative (0 if none)",
  "total_amount": "Number - final total paid amount",
  "currency": "String - default is 'MYR'",
  "payment_method": "String or null - one of: mastercard, visa, mykasih, cash, pay_with_qr, touchngo, other",
  "items": [
    {
      "description": "String - line item title",
      "quantity": "Number - unit quantity (supports decimals for kg / litres)",
      "unit_price": "Number - cost per single unit",
      "line_total": "Number - total price for this line after line discounts",
      "serial_number": "String or null - barcode / SKU / PLU / item code printed for this line",
      "suggested_category": "String - choose one of these exact categories: Food & Dining, Transportation & Fuel, Groceries & Household, Electronics & Gadgets, Utilities & Bills, Healthcare & Medical, Entertainment & Leisure, Office Supplies, Subscriptions & Memberships"
    }
  ]
}
PROMPT;
    }
}
