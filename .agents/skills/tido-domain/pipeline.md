# Receipt & Integration Pipeline

## End-to-end (image receipts)

```
WhatsApp image | Drive file | Filament upload | Manual create
        ↓
Invoice (status=pending, image_path set, source=…)
        ↓
InvoiceObserver::created → ExtractReceiptDataJob::dispatch(invoiceId)
  (WhatsApp waits for document-received ack first)
        ↓
OllamaService::parseReceipt(base64, ReceiptExtractionPrompt::build())
        ↓
Update Invoice fields + create InvoiceItems (label via LabelMatcher)
status = parsed | requires_manual_review
        ↓
InvoiceObserver / BudgetAlertService (threshold WhatsApp + DB notifications)
```

## End-to-end (WhatsApp manual text)

```
WhatsApp text (ManualWhatsAppInvoiceParser)
        ↓
ProcessManualWhatsAppInvoiceJob
  → Invoice (pending, no image, MYR, payment from token or cash)
  → InvoiceItems (label_id null)
        ↓
WhatsAppManualInvoiceReceivedDebouncer → Manual invoice received ack
        ↓
ParseManualWhatsAppInvoiceJob
  → OllamaService::generateJson(ManualInvoiceLabelPrompt)
  → LabelMatcher → label_id
  → status = requires_manual_review
        ↓
Manual invoice parsed WhatsApp reply (edit URL)
```

User-facing format and tokens: [docs/whatsapp-manual-invoice.md](../../../docs/whatsapp-manual-invoice.md).

## ExtractReceiptDataJob

- `$tries = 3`, backoff `[30, 60, 120]`
- Skip if invoice missing or status ≠ `pending`
- Blank `image_path` → skip (do not mark `failed`; used by manual text invoices)
- Missing file on disk → `failed`
- Empty Ollama parse → throw (retry); `failed()` → `requires_manual_review`
- Label: match AI `label` (legacy `suggested_category`) via `LabelMatcher` to Finance `Label`; leave null if unknown

## Duplicate hash

On `creating`:

```php
hash('sha256', $invoice_number . $date_time . $exact_total)
```

Unique on `receipt_hash`. Factories should set a unique hash.

## WhatsApp webhook

- Route: `POST /api/webhooks/whatsapp` (`routes/api.php`)
- Auth: `Authorization: Bearer {services.evolution.api_key}`
- Event: `messages.upsert`
- Sender allowlist: Profile `users.phone` + Family Members with `allowlist_enabled` (normalized); others → `ignored_sender` (no reply). Family members do not grant panel/OTP.
- Self-chat allowed when `remoteJid` matches allowlist (including `fromMe: true`)
- Image: fetch media → `receipts/` storage → pending Invoice → ack text
- Text: spend/total keywords → monthly sum via Evolution `sendText`
- Text manual invoice format (`merchant[, payment];` + `item, qty, line_total;` blocks, multi-block OK) → pending Invoice (no image; payment token optional: `qr` / `tngo` / `card` / `cash`…, default cash) → Manual invoice received ack → `ParseManualWhatsAppInvoiceJob` (Ollama labels only) → `requires_manual_review` + Manual invoice parsed reply

## Google Drive sync

- Schedule: every 15 minutes → `SyncGoogleDriveJob`
- List jpg/jpeg/png in configured folder → copy local → pending Invoice → delete remote
- Missing Drive credentials: Google disk falls back (see `AppServiceProvider`)

## Ollama client checklist

When editing `OllamaService` or adding AI calls:

- [ ] Payload includes `"format": "json"`
- [ ] Response cleaned of \`\`\`json fences before `json_decode`
- [ ] Timeout from `config('services.ollama.timeout')`
- [ ] Feature test with `Http::fake` covering success + garbage markdown
- [ ] Vision calls use `images`; text-only label calls omit `images` (`generateJson`)

## Horizon notes

Supervisors listen on `default`, `receipts`, `whatsapp`. Jobs today often use the default queue; assign `onQueue()` when isolating AI/WhatsApp load. Gate `viewHorizon` allowlist must be set for production dashboard access.

## Related docs

- Blueprint: `docs/system-architecture.md`
- Agent map: `docs/agent-onboarding.md`
- Manual WhatsApp text: `docs/whatsapp-manual-invoice.md`
- Ops: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/google-drive-setup.md`
