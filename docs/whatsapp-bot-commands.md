# WhatsApp bot commands

Allowlisted WhatsApp senders (Profile phone + allowlisted Family Members) can interact with the **tido** bot via text and media. This doc is the full command / keyword reference.

**In chat:** type `help` for a short overview, `manual` for manual invoice format, or `finance others` for the finance keyword list.

Setup: [evolution-local-windows.md](evolution-local-windows.md) · Manual invoice format detail: [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md)

## Routing priority

Inbound text is handled in this order:

1. **Manual invoice format** — structured text blocks (see [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md))
2. **Spend / total** — message contains `spend` or `total` (see below)
3. **`finance others`** — finance keyword reference reply
4. **`manual`** or **`manual way`** — manual invoice guide
5. **Anything else** — help reply

Images are handled separately (receipt upload → OCR pipeline).

## Receipt ingestion (no keyword)

| Action | What happens |
|--------|----------------|
| Send **image(s)** | Saved and queued for AI parsing → document received ack → document parsed reply with edit URL |
| Send **manual invoice text** | Fixed `merchant[, payment];` + `item, qty, total;` lines → manual invoice received ack → parsed reply |

Manual format rules and payment tokens: [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md).

## Help and guides

| Type in chat | Reply |
|--------------|-------|
| *(anything unrecognized)* | `help` — upload options, manual invoice hint, spend hint |
| `manual` or `manual way` | Manual approach — format, sample, supported payment method names |
| `finance others` | Finance keywords — full list of spending commands |

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
| Document/image received (batched) | Document received |
| Document parsed | Document parsed + merchant, total, payment method, edit URL |
| Manual invoice received (batched) | Manual invoice received |
| Manual invoice parsed | Manual invoice parsed + edit URL |
| Upload download failed | Upload failed (with retry hint) |
| Budget threshold crossed | Budget alert / Budget critical (proactive, Profile phone) |

## Key code

| Piece | Location |
|-------|----------|
| Webhook routing | `App\Http\Controllers\Api\WhatsAppWebhookController` |
| Message templates | `App\Support\WhatsAppMessage` |
| Spend command parser | `App\Support\WhatsAppSpendingCommandParser` |
| Spend reply builder | `App\Support\WhatsAppSpendingReplyBuilder` |
| Manual text parser | `App\Support\ManualWhatsAppInvoiceParser` |
| Analytics data | `App\Filament\Support\DashboardMonthAnalytics` |

## Related

- [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md) — manual invoice text format and pipeline
- [evolution-local-windows.md](evolution-local-windows.md) — Evolution API + webhook setup
- [`.agents/skills/tido-domain/pipeline.md`](../.agents/skills/tido-domain/pipeline.md) — ingestion pipeline detail
