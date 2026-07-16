# Ollama Setup Guide (native, no Docker)

Run Ollama on the Windows host so tido can parse receipt images with a local vision model. This matches the host pattern used for Evolution ([evolution-local-windows.md](evolution-local-windows.md)).

## Architecture (local)

| Process | How it runs | Role |
|---------|-------------|------|
| Ollama | Windows installer (background service) | Vision API on `http://127.0.0.1:11434` |
| tido | `npm run dev:full` | Vite + `artisan serve` + queue worker |

Upload → pending `Invoice` → `ExtractReceiptDataJob` → `OllamaService` → `POST /api/generate` → status `parsed`.

---

## Prerequisites

- Windows 10/11
- [Ollama for Windows](https://ollama.com/download)
- NVIDIA GPU + current Game Ready / Studio driver (recommended for speed; CPU works but is slower)
- tido running on the same machine with a queue worker (`npm run dev:full`)

No Docker, NVIDIA Container Toolkit, or Sail `ollama` service.

---

## Step 1: Install Ollama

1. Download and install from [https://ollama.com/download](https://ollama.com/download).
2. Accept the default so Ollama starts as a Windows service and listens on port **11434**.
3. Confirm the API is up:

```bash
curl http://127.0.0.1:11434/api/tags
```

You should get JSON (an empty `models` list is fine before the first pull).

Optional: start Ollama via `npm run dev:ollama` if the service is not already running.

---

## Step 2: Pull the vision model

tido defaults to **`qwen2.5vl:7b`** for receipt OCR on an RTX 4060 (8 GB) or similar. `minicpm-v` remains a lighter fallback.

```bash
ollama pull qwen2.5vl:7b
```

Confirm the model is listed:

```bash
curl http://127.0.0.1:11434/api/tags
```

---

## Step 3: Point tido at localhost

In `.env` (see also `.env.example`):

```env
OLLAMA_HOST=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5vl:7b
OLLAMA_TIMEOUT=120
```

If Ollama runs on another machine, set `OLLAMA_HOST` to that host's URL only.

After changing env values, restart `npm run dev:full` (or clear config cache if you use one).

---

## Step 4: Run tido with a queue worker

Parsing is asynchronous. The Filament upload only creates a pending invoice; the queue worker calls Ollama.

```bash
npm run dev:full
```

That starts Vite, `php artisan serve` (port 2000), and `queue:listen` on `default,whatsapp,receipts` with a timeout long enough for `OLLAMA_TIMEOUT=120`.

---

## Step 5: Smoke test

1. Open Filament → **Upload Receipts** and upload a receipt image.
2. Open the invoice: status should move from `pending` → `parsed` with merchant / amounts / line items.
3. If status stays `pending`, the queue worker is not running.
4. If status becomes `requires_manual_review`, check `storage/logs/laravel.log` for Ollama connection or HTTP errors.

### Optional: GPU check

While a receipt is parsing, run `nvidia-smi` on the host. You should see `ollama` or `ollama_llama_server` using GPU memory/compute.

---

## Config reference

| Env | Default | Purpose |
|-----|---------|---------|
| `OLLAMA_HOST` | `http://127.0.0.1:11434` | Base URL for `/api/generate` |
| `OLLAMA_MODEL` | `qwen2.5vl:7b` | Vision model name |
| `OLLAMA_TIMEOUT` | `120` | HTTP timeout (seconds) |

App wiring: `config/services.php` → `OllamaService` → `ExtractReceiptDataJob`.
