# WhatsApp manual invoices (text-only)

When a merchant does not issue a paper/digital receipt image, allowlisted WhatsApp senders can create invoices by sending a **fixed text format** (no image attachment).

## Format

```
Merchant Name[, payment];
Item description, quantity, line_total;
Item description, quantity, line_total;
```

Rules:

- Each line ends with `;`
- First line of a block = merchant (optional payment token after a comma)
- Following lines = `description, quantity, line_total`
- Blank line between blocks = multiple invoices in one message
- Rapid successive messages are batched into one “Manual invoice received” ack (same debounce idea as document uploads)

### Example

```
Kedai Makan Seri Ayu, qr;
Nasi + ikan keli goreng masak merah + telur dadar + salad + budu + kuah daging gulai, 1, 12;
Nasi separuh + telur dadar + sambal ikan bilis + ulam ulaman + budu, 1, 8;
Teh o ais, 1, 2.5;
Teh o ais limau, 1, 3.5;
```

### Payment tokens (optional)

Trailing merchant tokens map to **Payment Method aliases** configured under **Settings → Payment Methods** (plus each method’s slug).

System defaults (seeded):

| Token | Payment method |
|-------|----------------|
| *(omitted)* | Cash (default) |
| `cash` | Cash |
| `qr` | Pay with QR |
| `tngo` / `tng` | Touch 'n Go |
| `card` / `mc` / `mastercard` | Mastercard |
| `visa` | Visa |
| `mykasih` | MYKASIH |
| `other` | Other |

Custom methods: add aliases on the Payment Method record (e.g. GrabPay → `grab`) so the same WhatsApp token works without a code change.

Unknown trailing tokens are treated as part of the merchant name (not a payment method).

## What tido stores

| Field | Value |
|-------|--------|
| `merchant_name` | From merchant line (token stripped) |
| `total_amount` / `subtotal` | Sum of line totals |
| Tax / discount / rounding | `0.00` |
| `currency` | `MYR` |
| `payment_method_id` | From token/alias, else Cash |
| `source` | `whatsapp` |
| `image_path` | `null` (Recent Receipts shows **Manual invoice**, no file link) |
| `date_time` | Ingest time (`now()`); editable in admin |
| Item `unit_price` | `line_total / quantity` when qty ≠ 0 |
| `status` | `requires_manual_review` after label classification |

## Pipeline

```
WhatsApp text (manual format)
  → ProcessManualWhatsAppInvoiceJob
  → pending Invoice + InvoiceItems (labels null)
  → debounced Manual invoice received ack
  → ParseManualWhatsAppInvoiceJob (Ollama text JSON → Finance Labels)
  → status = requires_manual_review
  → Manual invoice parsed WhatsApp reply (edit URL)
```

Does **not** use vision OCR (`ExtractReceiptDataJob`). Image receipts remain the separate WhatsApp document path.

## Auto-replies

**Received** (batched):

```
📥 *Manual invoice received*

A total of *N* manual invoice(s) saved and queued for AI parsing.

— Powered by *tido*
```

**Parsed** (per invoice):

```
🎉 *Manual invoice parsed*

Merchant: *…*
Total Amount: *RM …*
Payment Method: *…*

Go to *invoice edit*
https://…/admin/invoices/{id}/edit

— Powered by *tido*
```

## Other WhatsApp text commands

- `spend` / `total` — month spending summary (MoM, forecast, top labels/merchants, budgets at risk)
- `spend labels` / `spend merchants` / `spend budgets` / `spend trend` / `spend payment` / `spend recent` — detailed breakdowns
- `spend march` / `spend 2025-03` / `spend last month` — same commands for a specific month
- `manual` / `manual way` — manual invoice format, sample, and supported payment methods
- `finance others` — finance keyword reference for spending commands
- Anything else (non-format) — help text (points to `manual` and `finance others` for details)

## Key code

| Piece | Location |
|-------|----------|
| Parser | `App\Support\ManualWhatsAppInvoiceParser` |
| Webhook | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| Create job | `App\Jobs\ProcessManualWhatsAppInvoiceJob` |
| Label job | `App\Jobs\ParseManualWhatsAppInvoiceJob` |
| Label prompt | `App\Prompts\ManualInvoiceLabelPrompt` |
| Messages | `App\Support\WhatsAppMessage` |
| Filename UI | `App\Helpers\FilenameDisplay::MANUAL_INVOICE_LABEL` |

## Related

- Pipeline detail: [`.agents/skills/tido-domain/pipeline.md`](../.agents/skills/tido-domain/pipeline.md)
- Evolution setup: [evolution-local-windows.md](evolution-local-windows.md)
- Architecture: [system-architecture.md](system-architecture.md)
