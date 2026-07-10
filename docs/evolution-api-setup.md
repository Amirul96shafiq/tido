# WhatsApp Integration Setup Guide (Evolution API)

Choose your environment:

| Setup | Guide |
|-------|--------|
| **Sail / Docker** (Linux or working Docker Desktop) | This document |
| **Windows host, no Docker** | [evolution-local-windows.md](evolution-local-windows.md) |

This guide documents how to set up the Evolution API **container**, pair your personal WhatsApp number using a QR code scan, and register the webhook URL to enable receipt ingestion and chatbot queries.

---

## Step 1: Start Evolution API Container
The Evolution API service is included in your `compose.yaml` configuration.

Run Sail to start it:
```bash
./vendor/bin/sail up -d
```

Once running, the service is accessible at `http://localhost:8085` (external port mapped to `8080` internally).

---

## Step 2: Create a WhatsApp Instance
You need to call the Evolution API endpoint to create a new WhatsApp connection instance named `tido`.

Using curl:
```bash
curl -X POST http://localhost:8085/instance/create \
  -H "Content-Type: application/json" \
  -H "apikey: tido-secret-key" \
  -d '{
    "instanceName": "tido",
    "token": "tido-secret-key",
    "qrcode": true
  }'
```

After a successful create, the response includes `qrcode.base64` (a data-URI PNG). Easiest: open tido **Settings → WhatsApp** and click **Generate / refresh QR** to display it in the admin UI.

Or use Evolution’s Swagger UI / paste the base64 into a browser address bar.

---

## Step 3: Scan the QR Code
1. Copy the base64 string from the API response or use the Evolution API built-in manager UI (if configured).
2. Alternatively, navigate to the Evolution API Swagger documentation at `http://localhost:8085/docs` and use the interactive builder to view the QR code in your browser.
3. Open WhatsApp on your personal phone -> **Linked Devices** -> **Link a Device**.
4. Scan the QR code. Once paired, your status will show as `CONNECTED`.

---

## Step 4: Register Webhook
Register your local webhook URL with Evolution API so that it posts incoming messages back to tido.

Using curl:
```bash
curl -X POST http://localhost:8085/webhook/set/tido \
  -H "Content-Type: application/json" \
  -H "apikey: tido-secret-key" \
  -d '{
    "enabled": true,
    "url": "http://laravel.test/api/webhooks/whatsapp",
    "headers": {
      "Authorization": "Bearer tido-secret-key"
    },
    "events": [
      "messages.upsert"
    ]
  }'
```

---

## Step 5: Configure tido Environment
Ensure your `.env` contains matching keys:
```env
EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=tido-secret-key
EVOLUTION_INSTANCE_NAME=tido
PERSONAL_WHATSAPP_NUMBER=60123456789
```

`PERSONAL_WHATSAPP_NUMBER` must be digits only (Malaysia E.164 without `+`, e.g. `6012…`). Use the same value for:

- Budget alert WhatsApp destination
- Admin `User.phone` (seeded from this env when present)
- Panel access allowlist (`User::canAccessPanel`)
- **WhatsApp webhook sender allowlist** — only this number can trigger bot replies / receipt import. Strangers get no auto-reply. Self-chat (“Message yourself”) is allowed when `remoteJid` matches this number (even if `fromMe` is true).

### Login OTP

1. Pair Evolution (steps 1–4) and set `PERSONAL_WHATSAPP_NUMBER`.
2. Ensure the admin user phone matches that number (re-seed or edit Profile).
3. Verify delivery: `php artisan whatsapp:ping` (or `./vendor/bin/sail artisan whatsapp:ping` under Sail)
4. Open `/admin/login`, enter the WhatsApp number, click **Send WhatsApp code**, then enter the 6-digit code.
5. If Evolution is down, use **Sign in with email & password** on the login page.

Remember-me keeps you signed in; `SESSION_LIFETIME` defaults to 7 days in `.env.example`.
