# WhatsApp Integration Setup Guide (Evolution API)

This guide documents how to set up the Evolution API container, pair your personal WhatsApp number using a QR code scan, and register the webhook URL to enable receipt ingestion and chatbot queries.

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
You need to call the Evolution API endpoint to create a new WhatsApp connection instance named `trackall`.

Using curl:
```bash
curl -X POST http://localhost:8085/instance/create \
  -H "Content-Type: application/json" \
  -H "apikey: trackall-secret-key" \
  -d '{
    "instanceName": "trackall",
    "token": "trackall-secret-key",
    "qrcode": true
  }'
```

The response will contain a base64 encoded QR code string under the key `qrcode.code`.

---

## Step 3: Scan the QR Code
1. Copy the base64 string from the API response or use the Evolution API built-in manager UI (if configured).
2. Alternatively, navigate to the Evolution API Swagger documentation at `http://localhost:8085/docs` and use the interactive builder to view the QR code in your browser.
3. Open WhatsApp on your personal phone -> **Linked Devices** -> **Link a Device**.
4. Scan the QR code. Once paired, your status will show as `CONNECTED`.

---

## Step 4: Register Webhook
Register your local webhook URL with Evolution API so that it posts incoming messages back to TrackAll.

Using curl:
```bash
curl -X POST http://localhost:8085/webhook/set/trackall \
  -H "Content-Type: application/json" \
  -H "apikey: trackall-secret-key" \
  -d '{
    "enabled": true,
    "url": "http://laravel.test/api/webhooks/whatsapp",
    "headers": {
      "Authorization": "Bearer trackall-secret-key"
    },
    "events": [
      "messages.upsert"
    ]
  }'
```

---

## Step 5: Configure TrackAll Environment
Ensure your `.env` contains matching keys:
```env
EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=trackall-secret-key
EVOLUTION_INSTANCE_NAME=trackall
```
