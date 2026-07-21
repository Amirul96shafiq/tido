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
EVOLUTION_INSTANCE_NAME=tido
PERSONAL_WHATSAPP_NUMBER=60123456789
```

Use your real number (digits only). Admin `User.phone` must match (Profile or tinker).

Use `http://127.0.0.1:8080` — the default in `config/services.php` and `.env.example`.

Restart `npm run dev:full` after changing `.env` (or clear config cache if you use it).

---

## Step 3: Create instance and link WhatsApp

**Preferred:** with tido running (`npm run dev:full`), open `/admin` → **Integrations → EvolutionAPI** → **Connect**:

- **Scan QR code** — scan from another screen (Linked Devices → Link a Device).
- **Pair with code** — enter the WhatsApp number to link, copy the code, then Linked Devices → **Link with phone number instead** (works on one phone).

`PERSONAL_WHATSAPP_NUMBER` is for alerts, OTP login, and bot allowlist — it can differ from the number you link to Evolution.

Or via curl (include `integration`):

```bash
curl -X POST http://127.0.0.1:8080/instance/create \
  -H "Content-Type: application/json" \
  -H "apikey: tido-secret-key" \
  -d "{\"instanceName\":\"tido\",\"token\":\"tido-secret-key\",\"qrcode\":true,\"integration\":\"WHATSAPP-BAILEYS\"}"
```

The JSON includes `qrcode.base64` — the admin page renders that as an image. Wait until status is **CONNECTED** / `open`.

Reconnect later if needed:

```bash
curl -X GET "http://127.0.0.1:8080/instance/connect/tido" \
  -H "apikey: tido-secret-key"
```

---

## Step 4: Register webhook (receipts / bot)

tido serves on port **2000** with `dev:full`:

```bash
curl -X POST http://127.0.0.1:8080/webhook/set/tido \
  -H "Content-Type: application/json" \
  -H "apikey: tido-secret-key" \
  -d "{\"enabled\":true,\"url\":\"http://127.0.0.1:2000/api/webhooks/whatsapp\",\"headers\":{\"Authorization\":\"Bearer tido-secret-key\"},\"events\":[\"messages.upsert\"]}"
```

Only `PERSONAL_WHATSAPP_NUMBER` plus optional `PERSONAL_WHATSAPP_EXTRA_NUMBERS` are allowlisted for bot replies. Self-chat (“Message yourself”) is supported when the JID matches an allowlisted number. Extra numbers cannot OTP-login or access the panel.

Inbound handling:

- **Image / document** — receipt upload + OCR
- **Manual invoice text** — structured `merchant[, payment];` + line items (no image); see [whatsapp-manual-invoice.md](whatsapp-manual-invoice.md)
- **`spend` / `total`** — monthly spending reply
- Other text — help

OTP login only needs outbound `sendText`; webhook is for inbound receipts/commands.

---

## Step 5: Verify

Terminal 1: `npm run dev:full`  
Terminal 2: Evolution running  

```bash
php artisan whatsapp:ping
```

You should receive a WhatsApp on `PERSONAL_WHATSAPP_NUMBER`. Then open `/admin/login`, enter that number, **Send WhatsApp code**, enter the OTP.

If Evolution is down, use **Sign in with email & password**.

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
| `whatsapp:ping` fails | Evolution up? `EVOLUTION_API_URL=http://127.0.0.1:8080`? API key match? |
| Connection refused | Wrong port; Evolution not started |
| OTP not received | Instance CONNECTED? Number matches `User.phone`? |
| Webhook never fires | URL must be `http://127.0.0.1:2000/...` while using `artisan serve` |
| Wrong Evolution URL in `.env` | Use `http://127.0.0.1:8080` |

Production later: run tido + Evolution as separate managed services on a Linux VPS, not `concurrently` on a desktop.
