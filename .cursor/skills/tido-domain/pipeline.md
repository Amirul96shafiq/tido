# Receipt & Integration Pipeline

## End-to-end (image receipts)

```
WhatsApp image | Drive file | Filament upload | Manual create
        ↓
Invoice (status=pending, image_path set, source=…, family_member_id?)
  WhatsApp → ExpenseSenderAttribution (allowlisted Family Member phone)
  Filament family user → acting user’s family_member_id
  Primary / unknown → null (Primary spender)
        ↓
ExpenseObserver::created → ExtractReceiptDataJob::dispatch(invoiceId)
  (WhatsApp waits for document-received ack first)
        ↓
OllamaService::parseReceipt(base64, ReceiptExtractionPrompt::build())
        ↓
Update Invoice fields + create ExpenseItems (label via LabelMatcher)
status = parsed | requires_manual_review
        ↓
ExpenseObserver / BudgetAlertService (threshold WhatsApp + DB notifications)
```

## End-to-end (WhatsApp PDF receipts)

```
WhatsApp documentMessage (application/pdf)
        ↓
ProcessWhatsAppMediaJob
  → download media → detect MIME → enforce PDF_MAX_BYTES / PDF_MAX_PAGES
  → inspect page count with PdfPageInspector (Poppler pdfinfo)
  → store original PDF as receipts/wa_<message-id>.pdf
  → register accepted document for the batched received acknowledgement
        ↓
SendWhatsAppDocumentReceivedAckJob
  → send acknowledgement → dispatch ExtractReceiptDataJob
        ↓
ReceiptDocumentPreparer → PdfPageRenderer (Poppler pdftocairo → JPEG pages)
        ↓
ExtractReceiptDataJob
  → Ollama page JSON (`PdfReceiptPagePrompt`)
  → merge multi-page JSON (`PdfReceiptMergePrompt`)
  → normalize invoice + match Labels / payment method
  → status = parsed | requires_manual_review
```

Defaults are 10 MB (`PDF_MAX_BYTES`) and 3 pages (`PDF_MAX_PAGES`). Password-protected and unreadable PDFs are rejected before invoice creation. If `pdfinfo` is unavailable during intake, the PDF may be stored with a null page count, but `pdftocairo` must still be available when the extraction job runs.

## End-to-end (WhatsApp manual text)

```
WhatsApp text (ManualWhatsAppExpenseParser)
        ↓
ProcessManualWhatsAppExpenseJob
  → Invoice (pending, no image, MYR, payment from token or cash, family_member_id from sender)
  → ExpenseItems (label_id null)
        ↓
WhatsAppManualInvoiceReceivedDebouncer → Manual expense received ack
        ↓
ParseManualWhatsAppExpenseJob
  → OllamaService::generateJson(ManualExpenseLabelPrompt)
  → LabelMatcher → label_id
  → status = requires_manual_review
        ↓
Manual expense parsed WhatsApp reply (edit URL)
```

User-facing format and tokens: [docs/whatsapp-manual-expense.md](../../../docs/whatsapp-manual-expense.md).

## ExtractReceiptDataJob

- PDF invoices use Poppler `pdfinfo` / `pdftocairo`; each page is extracted separately and multi-page results are merged before normalization

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
- Auth: `Authorization: Bearer {services.evolution.webhook_secret}`; this inbound credential must be distinct from the outbound `services.evolution.api_key`
- Event: `messages.upsert`
- Sender allowlist: Profile `users.phone` + Family Members with `allowlist_enabled` (normalized); others → `ignored_sender` (no reply)
- Panel login: Family Members with `login_enabled` get a linked `User` and may OTP-login — see `docs/household-access.md`
- Self-chat allowed when `remoteJid` matches allowlist (including `fromMe: true`)
- Image: fetch media → `receipts/` storage → pending Expense (`family_member_id` via `ExpenseSenderAttribution`) → ack text
- Text: spend/total keywords → monthly sum via Evolution `sendText`
- Text manual expense format (`merchant[, payment];` + `item, qty, line_total;` blocks, multi-block OK) → pending Expense (no image; attributed; payment token optional: `qr` / `tngo` / `card` / `cash`…, default cash) → Manual expense received ack → `ParseManualWhatsAppExpenseJob` (Ollama labels only) → `requires_manual_review` + Manual expense parsed reply

## WhatsApp identity resolution

- Classic phone JIDs resolve by phone; `@lid` JIDs resolve only after `WhatsAppLid` links them to an allowlisted Primary or Family Member.
- Unlinked LIDs are remembered as pending for the Evolution API page and ignored by the webhook until linked or dismissed.

## Google Drive sync

- Schedule: every 15 minutes → `SyncGoogleDriveJob`
- List jpg/jpeg/png in configured folder → copy local → pending Expense → delete remote
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
- Manual WhatsApp text: `docs/whatsapp-manual-expense.md`
- Ops: `docs/ollama-setup.md`, `docs/evolution-local-windows.md`, `docs/google-drive-setup.md`
