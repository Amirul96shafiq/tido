<?php

declare(strict_types=1);

namespace App\Prompts;

class ReceiptExtractionPrompt
{
    public static function get(): string
    {
        return <<<PROMPT
Please extract financial information from this receipt image. 
You must respond with a raw JSON object only. Do not wrap it in markdown formatting (like ```json).

The output JSON structure MUST match this exact schema:
{
  "merchant_name": "String - the store or company name",
  "invoice_number": "String or null - invoice or receipt reference number",
  "date_time": "String - ISO 8601 formatted date and time (e.g. YYYY-MM-DD HH:MM:SS)",
  "subtotal": "Number - pre-tax total amount",
  "total_tax": "Number - total tax amount (SST / service tax / GST)",
  "total_amount": "Number - final total paid amount",
  "currency": "String - default is 'MYR'",
  "items": [
    {
      "description": "String - line item title",
      "quantity": "Number - unit quantity",
      "unit_price": "Number - cost per single unit",
      "line_total": "Number - total price for this line",
      "suggested_category": "String - choose one of these exact categories: Food & Dining, Transportation & Fuel, Groceries & Household, Electronics & Gadgets, Utilities & Bills, Healthcare & Medical, Entertainment & Leisure, Office Supplies, Subscriptions & Memberships"
    }
  ]
}
PROMPT;
    }
}
