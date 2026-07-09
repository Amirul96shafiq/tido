# Receipt & Integration Pipeline

## End-to-end

```
WhatsApp image | Drive file | Filament upload | Manual create
        ↓
Invoice (status=pending, image_path set, source=…)
        ↓
InvoiceObserver::created → ExtractReceiptDataJob::dispatch(invoiceId)
        ↓
OllamaService::parseReceipt(base64, ReceiptExtractionPrompt::get())
        ↓
Update Invoice fields + create InvoiceItems (labeling by slug)
status = parsed
        ↓
InvoiceObserver / BudgetAlertService (threshold WhatsApp + DB notifications)
```

## ExtractReceiptDataJob

- `$tries = 3`, backoff `[30, 60, 120]`
- Skip if invoice missing or status ≠ `pending`
- Missing image → `failed`
- Empty Ollama parse → throw (retry); `failed()` → `requires_manual_review`
- Category: match `suggested_category` to Finance `Labeling` slug; leave null if unknown

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
- Sender allowlist: only `PERSONAL_WHATSAPP_NUMBER` (normalized) is processed; others → `ignored_sender` (no reply)
- Self-chat allowed when `remoteJid` matches allowlist (including `fromMe: true`)
- Image: fetch media → `receipts/` storage → pending Invoice → ack text
- Text: spend/total keywords → monthly sum via Evolution `sendText`

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

## Horizon notes

Supervisors listen on `default`, `receipts`, `whatsapp`. Jobs today often use the default queue; assign `onQueue()` when isolating AI/WhatsApp load. Gate `viewHorizon` allowlist must be set for production dashboard access.

## Related docs

- Blueprint: `docs/system-architecture.md`
- Agent map: `docs/agent-onboarding.md`
- Ops: `docs/ollama-setup.md`, `docs/evolution-api-setup.md`, `docs/google-drive-setup.md`
