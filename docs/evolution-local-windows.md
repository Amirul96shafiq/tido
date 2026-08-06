# Evolution API (WhatsApp) — canonical setup for tido

Run **tido** and **Evolution** as two separate processes on the Windows host.

## Architecture (local)

| Terminal | Command | Role |
|----------|---------|------|
| 1 | `npm run dev:full` | tido (Vite + `artisan serve` :2000 + queue) |
| 2 | `npm run evolution` | Evolution API on `http://127.0.0.1:8080` |

Optional later: `npm run dev:whatsapp` starts tido **and** Evolution in one window (Approach A). Prefer two terminals until QR pairing works.

Clone Evolution **outside** this repo, e.g. `g:\projects\evolution-api` (sibling of `tido`).

---

## Prerequisites

- **Node.js 20+** ([nodejs.org](https://nodejs.org/) or `nvm-windows`)
- **PostgreSQL** or **MySQL** for Evolution (separate from tido’s SQLite)
- **Redis** (recommended by Evolution; install via Memurai, Redis Windows port, or a small local Redis)
- **Poppler for Windows** with `pdfinfo.exe` and `pdftocairo.exe` for WhatsApp PDF receipt parsing; see [Ollama setup](ollama-setup.md#pdf-receipt-parsing)
- Git

Official Evolution docs: [docs.evolutionfoundation.com.br](https://docs.evolutionfoundation.com.br)

---

## Step 1: Clone and install Evolution

```bash
cd g:/projects
git clone https://github.com/evolution-foundation/evolution-api.git
cd evolution-api
npm install
cp .env.example .env
```

Edit Evolution’s `.env` at minimum:

- `AUTHENTICATION_API_KEY` — long random secret; **must match** tido’s `EVOLUTION_API_KEY` exactly
- Database provider + connection string (Postgres/MySQL)
- Redis URL if required
- Server port `8080` (default)
- Linked device label (optional; `npm run evolution` defaults these):
  - `CONFIG_SESSION_PHONE_CLIENT="tido App (Evolution API)"` — os string WhatsApp shows
  - `CONFIG_SESSION_PHONE_NAME=Desktop` — PlatformType for **QR** links (`Chrome` forces a “Google Chrome (…)” prefix)
- Pair with code uses Evolution’s stock Baileys path (no custom browser identity). Linked Devices typically shows `Google Chrome (Mac OS)`. Use **QR** if you want `tido App (Evolution API)` as the device label.
- After changing those values: **Log out** the linked device on your phone, restart Evolution, then connect again (QR or pairing code). Existing links keep the old name.
- If WhatsApp shows **Google Chrome (Mac OS)** after pairing with a code, Evolution was skipping the custom `browser` identity on the pairing path (Baileys default). Use a build that sets CLIENT + Chrome for pairing, then logout and re-pair.

Then:

```bash
# Example for PostgreSQL — follow Evolution README for your provider
export DATABASE_PROVIDER=postgresql   # Git Bash; on PowerShell use $env:DATABASE_PROVIDER=postgresql
npm run db:generate
npm run db:deploy
```

Start Evolution:

```bash
npm run dev:server
# or: npm run build && npm run start:prod
```

Confirm [http://127.0.0.1:8080](http://127.0.0.1:8080) (or `/docs` if exposed) responds.

From the **tido** repo you can also start it with:

```bash
# default path: ../evolution-api
npm run evolution

# custom path:
EVOLUTION_PATH=g:/projects/evolution-api npm run evolution
```

On Windows PowerShell:

```powershell
$env:EVOLUTION_PATH="g:\projects\evolution-api"; npm run evolution
```

---

## Step 2: Point tido at host Evolution

In tido's `.env`:

```env
EVOLUTION_API_URL=http://127.0.0.1:8080
EVOLUTION_API_KEY=<same long secret as Evolution AUTHENTICATION_API_KEY>
EVOLUTION_WEBHOOK_SECRET=<different long random secret used only for Evolution webhook callbacks>
EVOLUTION_INSTANCE_NAME=tido
```

`EVOLUTION_API_KEY` authenticates tido's outbound requests to Evolution. `EVOLUTION_WEBHOOK_SECRET` authenticates inbound requests from Evolution to tido and must be generated, stored, and rotated separately.

Set your WhatsApp number in **Profile** (required). Optional family contacts: **Settings → Family Members** with “Include in contact allowlist”.

For PDF receipts, configure the Poppler executable paths in `.env`. Queue workers should use absolute Windows paths because their inherited `PATH` can differ from the web process:

```env
PDF_MAX_BYTES=10485760
PDF_MAX_PAGES=3
PDF_INSPECTION_TIMEOUT=15
PDF_RENDER_TIMEOUT=60
PDF_RENDER_DPI=144
PDFINFO_BINARY=C:/path/to/poppler/Library/bin/pdfinfo.exe
PDFTOCAIRO_BINARY=C:/path/to/poppler/Library/bin/pdftocairo.exe
```

Restart `npm run dev:full` after changing these values. The defaults accept PDFs up to 10 MB and 3 pages; adjust only when the queue worker has enough memory and Ollama context for the larger document.

Use `http://127.0.0.1:8080` — the default in `config/services.php` and `.env.example`.

Restart `npm run dev:full` after changing `.env` (or clear config cache if you use it).

---

## Step 3: Create instance and link WhatsApp

**Preferred:** with tido running (`npm run dev:full`), open `/admin` → **Integrations → Evolution API** → **Connect** (requires Profile WhatsApp number first):

- **Scan QR code** — scan from another screen (Linked Devices → Link a Device).
- **Pair with code** — enter the WhatsApp number to link, copy the code, then Linked Devices → **Link with phone number instead** (works on one phone).

Your **Profile** WhatsApp number is for alerts, OTP login, and the bot allowlist — it can differ from the number you link to Evolution. Family Members with allowlist enabled can also talk to the bot (not OTP/panel).

Or via curl (include `integration`):

In the shell used for these commands, set `EVOLUTION_API_KEY` to the outbound value configured in both applications and set `EVOLUTION_WEBHOOK_SECRET` to the separate inbound value configured in tido. Do not paste either value into documentation or source files.

```bash
curl -X POST http://127.0.0.1:8080/instance/create \
  -H "Content-Type: application/json" \
  -H "apikey: ${EVOLUTION_API_KEY}" \
  -d "{\"instanceName\":\"tido\",\"token\":\"${EVOLUTION_API_KEY}\",\"qrcode\":true,\"integration\":\"WHATSAPP-BAILEYS\"}"
```

The JSON includes `qrcode.base64` — the admin page renders that as an image. Wait until status is **CONNECTED** / `open`.

Reconnect later if needed:

```bash
curl -X GET "http://127.0.0.1:8080/instance/connect/tido" \
  -H "apikey: ${EVOLUTION_API_KEY}"
```

---

## Step 4: Register webhook (receipts / bot)

tido serves on port **2000** with `dev:full`:

```bash
curl -X POST http://127.0.0.1:8080/webhook/set/tido \
  -H "Content-Type: application/json" \
  -H "apikey: ${EVOLUTION_API_KEY}" \
  -d "{\"enabled\":true,\"url\":\"http://127.0.0.1:2000/api/webhooks/whatsapp\",\"headers\":{\"Authorization\":\"Bearer ${EVOLUTION_WEBHOOK_SECRET}\"},\"events\":[\"messages.upsert\"]}"
```

Only Profile WhatsApp numbers plus Family Members with allowlist enabled are allowlisted for bot replies. Self-chat (“Message yourself”) is supported when the JID matches an allowlisted number. Family members with **login enabled** can sign in to `/admin` via WhatsApp OTP on their own number (limited Finances access).

**Local testing without a second WhatsApp:** set `WHATSAPP_LOGIN_DEV_OTP=123456` and `WHATSAPP_LOGIN_DEV_PHONES=60111222333` in `.env` (local/testing only). `DatabaseSeeder` seeds **Sample Spouse** on that number — send OTP on login, then enter the dev code (no Evolution send).

Inbound handling — full command list: [whatsapp-bot-commands.md](whatsapp-bot-commands.md). Household roles / family login: [household-access.md](household-access.md).

- **Image / PDF document** — receipt upload + OCR. Images are parsed directly; accepted PDFs are rendered page-by-page with Poppler before Ollama extraction. Non-PDF document types are ignored.
- **Manual invoice text** — structured `merchant[, payment];` + line items (no image); see [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md)
- **`spend` / `total`** and sub-commands — spending analytics replies
- **`manual`**, **`finance others`**, or other text — guides / help

OTP login only needs outbound `sendText`; webhook is for inbound receipts/commands.

### WhatsApp LID allowlist

WhatsApp can deliver an inbound chat with a Linked ID (LID), such as `3693839708391@lid`, instead of the classic phone JID. A LID is an opaque WhatsApp identity and is not treated as a phone number automatically.

When an unlinked LID sends an inbound message:

1. The webhook ignores the sender and sends no reply.
2. The LID and optional WhatsApp push name are remembered as a pending identity for up to 30 days.
3. Open **Integrations → Evolution API → WhatsApp LID**.
4. Use **Link LID** to map the pending identity to the Primary contact or an allowlisted Family Member.
5. The contact card then shows the linked LID. Future messages from that LID resolve to the contact’s allowlisted phone and can use bot, receipt, and attribution behavior normally.

Use **Dismiss** for an identity that should not be linked. Use **Unlink** on a linked contact to remove the mapping. Linking requires a configured Primary Profile WhatsApp number for the Primary target and an allowlisted Family Member for a family target.

---

## Step 5: Verify

Terminal 1: `npm run dev:full`  
Terminal 2: Evolution running  

```bash
php artisan whatsapp:ping
```

You should receive a WhatsApp on your Profile number. Then open `/admin/login`, enter that number, **Send WhatsApp code**, enter the OTP.

For the seeded family test member (`60111222333` / `0111222333`) with dev OTP configured, no WhatsApp is sent — use `123456`.

If Evolution is down, use **Sign in with email & password** (primary user only).

---

## npm scripts (tido)

| Script | Purpose |
|--------|---------|
| `npm run dev:full` | tido only (default daily work) |
| `npm run evolution` | Start Evolution from `EVOLUTION_PATH` (default `../evolution-api`) |
| `npm run dev:whatsapp` | tido + Evolution together (opt-in) |

---

## Troubleshooting

| Issue | Check |
|-------|--------|
| `whatsapp:ping` fails | Evolution up? `EVOLUTION_API_URL=http://127.0.0.1:8080`? Does `EVOLUTION_API_KEY` match `AUTHENTICATION_API_KEY`? |
| Connection refused | Wrong port; Evolution not started |
| OTP not received | Instance CONNECTED? Number matches `User.phone`? |
| Webhook never fires | URL must be `http://127.0.0.1:2000/...` while using `artisan serve`; confirm the registered `Authorization` header uses `EVOLUTION_WEBHOOK_SECRET` |
| Wrong Evolution URL in `.env` | Use `http://127.0.0.1:8080` |
| PDF is rejected as unreadable or password-protected | Resend an unencrypted PDF; password-protected and unreadable PDFs are not supported |
| PDF remains pending or ends in manual review | Confirm `PDFINFO_BINARY` and `PDFTOCAIRO_BINARY` point to working Poppler executables, then restart the queue worker |
| LID sender is ignored | Open the WhatsApp LID section and link the pending LID to the correct allowlisted contact |

Production later: run tido + Evolution as separate managed services on a Linux VPS, not `concurrently` on a desktop.
