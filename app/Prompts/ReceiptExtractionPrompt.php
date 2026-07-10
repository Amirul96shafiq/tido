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

The output JSON structure MUST match this exact schema:
{
  "merchant_name": "String - prefer store brand and branch (e.g. FamilyMart Pinggiran Batu Caves)",
  "invoice_number": "String or null - invoice or receipt reference number",
  "date_time": "String - ISO 8601 formatted date and time (e.g. YYYY-MM-DD HH:MM:SS)",
  "subtotal": "Number - pre-tax / pre-rounding merchandise total",
  "total_tax": "Number - total SST / service tax / GST (include service charge if not split)",
  "discount_total": "Number - total discounts and savings (0 if none)",
  "rounding_amount": "Number - rounding adjustment, may be negative (0 if none)",
  "total_amount": "Number - final total paid amount",
  "currency": "String - default is 'MYR'",
  "payment_method": "String or null - one of: mastercard, visa, mykasih, cash, other",
  "items": [
    {
      "description": "String - line item title",
      "quantity": "Number - unit quantity (supports decimals for kg / litres)",
      "unit_price": "Number - cost per single unit",
      "line_total": "Number - total price for this line after line discounts",
      "suggested_category": "String - choose one of these exact categories: Food & Dining, Transportation & Fuel, Groceries & Household, Electronics & Gadgets, Utilities & Bills, Healthcare & Medical, Entertainment & Leisure, Office Supplies, Subscriptions & Memberships"
    }
  ]
}
PROMPT;
    }
}
