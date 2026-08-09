# WhatsApp bot commands

Allowlisted WhatsApp senders (Profile phone + allowlisted Family Members) can interact with the **tido** bot via text and media. WhatsApp image and PDF receipts are attributed to the sender (Primary vs Family Member). Family members with **login enabled** can sign in to `/admin` with their WhatsApp OTP (limited panel access).

**In chat:** type `help` for a short overview, `manual` for manual expense format, or `finance others` for the finance keyword list.

Setup: [evolution-local-windows.md](evolution-local-windows.md) · Manual expense format detail: [whatsapp-manual-expense.md](whatsapp-manual-expense.md)

## Routing priority

Inbound text is handled in this order:

1. **Manual expense format** — structured text blocks (see [whatsapp-manual-expense.md](whatsapp-manual-expense.md))
2. **Spend / total** — message contains `spend` or `total` (see below)
3. **`finance others`** — finance keyword reference reply
4. **`manual`** or **`manual way`** — manual expense guide
5. **Anything else** — help reply

Images and PDF documents are handled separately (receipt upload → OCR pipeline). Text commands are routed only after the sender has passed the phone or linked-LID allowlist check.

## Receipt ingestion (no keyword)

| Action | What happens |
|--------|----------------|
| Send **image(s) or PDF(s)** | Validated, saved, and queued for AI parsing → attributed to sender (Primary vs Family Member) → document received ack → document parsed/review reply with edit URL |
| Send **manual expense text** | Fixed `merchant[, payment];` + `item, qty, total;` lines → attributed → manual expense received ack → parsed reply |

Manual format rules and payment tokens: [whatsapp-manual-expense.md](whatsapp-manual-expense.md). Household attribution + panel login: [household-access.md](household-access.md).

## Help and guides

| Type in chat | Reply |
|--------------|-------|
| *(anything unrecognized)* | `help` — upload options, manual expense hint, spend hint |
| `manual` or `manual way` | Manual approach — format, sample, supported payment method names |
| `finance others` | Finance keywords — full list of spending commands |

## PDF receipt handling

PDF receipts are accepted only as `application/pdf` documents. The default limits are:

- Maximum file size: **10 MB** (`PDF_MAX_BYTES`)
- Maximum page count: **3** (`PDF_MAX_PAGES`)
- PDF inspection: Poppler `pdfinfo`
- Page rendering: Poppler `pdftocairo` to JPEG at `PDF_RENDER_DPI` (default 144), with `pdftoppm` fallback

The original PDF is stored on the expense. During extraction, each rendered page is sent to Ollama as a page-specific JSON request; multi-page results are merged before the normal invoice normalization and Label matching step. Password-protected, unreadable, non-PDF, oversized, and over-page-limit documents are not parsed. Rejected PDF details are included in the batched **Document received** acknowledgement, while accepted files are queued after that acknowledgement.

Configure Poppler with absolute paths in `.env` (`PDFINFO_BINARY`, `PDFTOCAIRO_BINARY`, and `PDFTOPPM_BINARY`) and restart the queue worker after changing them. See [evolution-local-windows.md](evolution-local-windows.md) and [ollama-setup.md](ollama-setup.md#pdf-receipt-parsing).

## Spending commands

Any message containing **`spend`** or **`total`** triggers a spending reply. Sub-commands and month filters are parsed from the same message.

### Summary (default)

| Command | Reply |
|---------|-------|
| `spend` | Current month summary |
| `total` | Same as `spend` |
| `How much did I spend this month?` | Same (contains `spend`) |

**Default summary includes:** period, total spent, change vs previous month, receipts processed/pending, end-of-month forecast (current month) or daily average (past months), top 3 labels, top 3 merchants, budgets at warn/critical (up to 3).

### Detailed breakdowns

| Command | Reply |
|---------|-------|
| `spend labels` | Label breakdown (up to 8) |
| `spend merchants` | Top 5 merchants |
| `spend budgets` | All active budgets with spent / limit / % |
| `spend trend` | Last 6 months spending |
| `spend payment` | Spending by payment method (top 5) |
| `spend recent` | Last 5 receipts uploaded in the month |
| `spend last` | Same as `spend recent` |

**Aliases** (same mode): `label` / `categories`, `merchant` / `shops`, `budget`, `history`, `payments`, `receipts`.

### Month selection

Combine any spending command with a period:

| Example | Period |
|---------|--------|
| `spend last month` | Previous calendar month |
| `spend 2025-03` or `spend 2025/3` | March 2025 |
| `spend march` | March of current year (or prior year if that month is still in the future) |
| `spend labels march 2024` | Label breakdown for March 2024 |

If no month is given, **current month** is used.

## Pipeline auto-replies (not commands)

These are sent by the bot after ingestion jobs complete (no keyword needed):

| Event | Message |
|-------|---------|
| Document/image/PDF received (batched) | Document received; rejected PDFs include the filename and reason |
| Document parsed | Document parsed + merchant, total, payment method, edit URL |
| Manual expense received (batched) | Manual expense received |
| Manual expense parsed | Manual expense parsed + edit URL |
| Upload download failed | Upload failed (with retry hint) |
| Budget threshold crossed | Budget alert / Budget critical (proactive, Profile phone) |

## Key code

| Piece | Location |
|-------|----------|
| Webhook routing | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| Message templates | `App\Support\WhatsAppMessage` |
| Spend command parser | `App\Support\WhatsAppSpendingCommandParser` |
| Spend reply builder | `App\Support\WhatsAppSpendingReplyBuilder` |
| Manual text parser | `App\Support\ManualWhatsAppExpenseParser` |
| Sender attribution | `App\Support\ExpenseSenderAttribution` |
| Analytics data | `App\Filament\Support\DashboardMonthAnalytics` |

## Related

- [household-access.md](household-access.md) — attribution, family OTP login, panel ACL
- [whatsapp-manual-expense.md](whatsapp-manual-expense.md) — manual expense text format and pipeline
- [evolution-local-windows.md](evolution-local-windows.md) — Evolution API + webhook setup
- [`.agents/skills/tido-domain/pipeline.md`](../.agents/skills/tido-domain/pipeline.md) — ingestion pipeline detail
