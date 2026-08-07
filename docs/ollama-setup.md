# Ollama Setup Guide (native, no Docker)

Run Ollama on the Windows host so tido can parse receipt images and rendered PDF pages with a local vision model. This matches the host pattern used for Evolution ([evolution-local-windows.md](evolution-local-windows.md)).

## Architecture (local)

| Process | How it runs | Role |
|---------|-------------|------|
| Ollama | Windows installer (background service) | Vision API on `http://127.0.0.1:11434` |
| Poppler | Windows command-line tools | `pdfinfo` page inspection + `pdftotext` currency text extraction + `pdftocairo` PDF-to-JPEG rendering |
| tido | `npm run dev:full` | Vite + `artisan serve` + queue worker |

Upload → pending `Invoice` → `ExtractReceiptDataJob` → `OllamaService` → `POST /api/generate` → status `parsed`.

---

## Prerequisites

- Windows 10/11
- [Ollama for Windows](https://ollama.com/download)
- Poppler for Windows with `pdfinfo.exe`, `pdftotext.exe`, and `pdftocairo.exe` (the first and third are required for WhatsApp PDF receipts; `pdftotext.exe` supplies embedded currency evidence)
- NVIDIA GPU + current Game Ready / Studio driver (recommended for speed; CPU works but is slower)
- tido running on the same machine with a queue worker (`npm run dev:full`)

No Docker or NVIDIA Container Toolkit required.

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

tido defaults to **`qwen2.5vl:7b`** for receipt OCR on an RTX 4060 (8 GB) or similar.

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

## PDF receipt parsing

WhatsApp PDF receipts are stored as the original PDF and rendered page-by-page before Ollama extraction. The queue worker uses Poppler’s `pdfinfo` to inspect the page count, `pdftotext` to read embedded currency evidence when available, and `pdftocairo` to render JPEG pages. Multi-page results are extracted as page-level JSON and merged before the normal invoice normalization step.

Install a Windows Poppler distribution that includes the executables, then set absolute paths in `.env`:

```env
PDF_MAX_BYTES=10485760
PDF_MAX_PAGES=3
PDF_INSPECTION_TIMEOUT=15
PDF_RENDER_TIMEOUT=60
PDF_TEXT_TIMEOUT=15
PDF_RENDER_DPI=144
PDFINFO_BINARY=C:/path/to/poppler/Library/bin/pdfinfo.exe
PDFTOCAIRO_BINARY=C:/path/to/poppler/Library/bin/pdftocairo.exe
PDFTOPPM_BINARY=C:/path/to/poppler/Library/bin/pdftoppm.exe
PDFTOTEXT_BINARY=C:/path/to/poppler/Library/bin/pdftotext.exe
```

Absolute paths are recommended on Windows because queue workers may inherit a different `PATH` from the web process. The defaults accept PDFs up to 10 MB and 3 pages. Password-protected, unreadable, oversized, and over-page-limit PDFs are rejected before AI parsing; if `pdfinfo` is unavailable, the document is stored for later parsing, and page rendering falls back from `pdftocairo` to `pdftoppm` when configured.

---

## Step 4: Run tido with a queue worker

Parsing is asynchronous. The Filament upload only creates a pending invoice; the queue worker calls Ollama.

```bash
npm run dev:full
```

That starts Vite, `php artisan serve` (port 2000), and `queue:listen` on `default,whatsapp,receipts` with a timeout long enough for `OLLAMA_TIMEOUT=120`.

---

## Step 5: Smoke test

1. Open Filament → **Upload Receipts** and upload a receipt image, or send an image/PDF from an allowlisted WhatsApp number.
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
| `PDF_MAX_BYTES` | `10485760` | Maximum accepted PDF size in bytes |
| `PDF_MAX_PAGES` | `3` | Maximum accepted PDF pages |
| `PDFINFO_BINARY` | `pdfinfo` | Poppler page-count executable; use an absolute Windows path when needed |
| `PDFTOCAIRO_BINARY` | `pdftocairo` | Poppler PDF renderer; use an absolute Windows path when needed |
| `PDFTOPPM_BINARY` | `pdftoppm` | Fallback Poppler PDF renderer when `pdftocairo` is unavailable; use an absolute Windows path when needed |
| `PDFTOTEXT_BINARY` | `pdftotext` | Poppler embedded-text extractor used for currency evidence; use an absolute Windows path when needed |
| `PDF_INSPECTION_TIMEOUT` | `15` | `pdfinfo` timeout in seconds |
| `PDF_RENDER_TIMEOUT` | `60` | `pdftocairo` timeout in seconds |
| `PDF_TEXT_TIMEOUT` | `15` | `pdftotext` timeout in seconds |
| `PDF_RENDER_DPI` | `144` | JPEG render resolution; minimum effective value is 72 |

App wiring: `config/services.php` → `PdfPageInspector` / `PdfTextExtractor` / `PdfPageRenderer` → `ReceiptDocumentPreparer` → `ExtractReceiptDataJob` → `OllamaService`.
