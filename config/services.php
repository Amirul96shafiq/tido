<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5vl:7b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
        // Keep enough context for long receipts to finish their itemized JSON response.
        // Raising this grows the KV cache, so lower it only when the GPU has limited VRAM.
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
        // Long-edge cap for vision prompts; qwen2.5vl spends ~1 token per 28x28 pixel block,
        // so full-resolution uploads can fill num_ctx and leave no budget for the JSON answer.
        'max_image_dimension' => (int) env('OLLAMA_MAX_IMAGE_DIMENSION', 1280),
    ],

    'currencyapi' => [
        'provider' => env('CURRENCY_API_PROVIDER', 'currencyapi'),
        'base_url' => env('CURRENCY_API_BASE_URL', 'https://api.currencyapi.com'),
        'api_key' => env('CURRENCY_API_KEY'),
        'timeout' => (int) env('CURRENCY_API_TIMEOUT', 10),
        'connect_timeout' => (int) env('CURRENCY_API_CONNECT_TIMEOUT', 3),
        'cainfo' => env('CURRENCY_API_CAINFO'),
        'retry_delays' => [100, 500, 1000],
        'cache_ttl' => (int) env('CURRENCY_API_CACHE_TTL', 86400),
        // Sparkline samples (not one request per day). Free tier has no /v3/range.
        'series_max_points' => (int) env('CURRENCY_API_SERIES_MAX_POINTS', 7),
        // Series changes slowly; keep longer than latest to protect monthly quota.
        'series_cache_ttl' => (int) env('CURRENCY_API_SERIES_CACHE_TTL', 604800),
    ],

    'documents' => [
        'max_bytes' => (int) env('PDF_MAX_BYTES', 10 * 1024 * 1024),
        'max_pdf_pages' => (int) env('PDF_MAX_PAGES', 4),
        'pdfinfo_binary' => env('PDFINFO_BINARY', 'pdfinfo'),
        'pdftocairo_binary' => env('PDFTOCAIRO_BINARY', 'pdftocairo'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdf_inspection_timeout' => (int) env('PDF_INSPECTION_TIMEOUT', 15),
        'pdf_render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 60),
        'pdf_text_timeout' => (int) env('PDF_TEXT_TIMEOUT', 15),
        'pdf_render_dpi' => (int) env('PDF_RENDER_DPI', 144),
    ],

    'evolution' => [
        'api_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8080'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'tido'),
        'timeout' => (int) env('EVOLUTION_TIMEOUT', 15),
        'connect_timeout' => (int) env('EVOLUTION_CONNECT_TIMEOUT', 5),
        // Outbound sendText jobs (webhook replies) per minute across workers.
        'outbound_send_attempts_per_minute' => (int) env('EVOLUTION_OUTBOUND_SEND_ATTEMPTS_PER_MINUTE', 30),
        // Comma-separated exact IPs allowed to POST the WhatsApp webhook. Empty = fail-closed (403).
        // Default loopback matches Evolution on the same host. Behind a proxy, list Evolution's true source IP.
        'webhook_allowed_ips' => env('EVOLUTION_WEBHOOK_ALLOWED_IPS', '127.0.0.1,::1'),
        'webhook_max_body_bytes' => (int) env('EVOLUTION_WEBHOOK_MAX_BODY_BYTES', 262144),
        'webhook_max_text_chars' => (int) env('EVOLUTION_WEBHOOK_MAX_TEXT_CHARS', 8192),
        'webhook_message_id_max' => (int) env('EVOLUTION_WEBHOOK_MESSAGE_ID_MAX', 128),
        'webhook_per_ip_attempts_per_minute' => (int) env('EVOLUTION_WEBHOOK_PER_IP_ATTEMPTS_PER_MINUTE', 60),
        'webhook_global_attempts_per_minute' => (int) env('EVOLUTION_WEBHOOK_GLOBAL_ATTEMPTS_PER_MINUTE', 60),
        'webhook_per_sender_attempts_per_minute' => (int) env('EVOLUTION_WEBHOOK_PER_SENDER_ATTEMPTS_PER_MINUTE', 20),
        'webhook_idempotency_ttl_seconds' => (int) env('EVOLUTION_WEBHOOK_IDEMPOTENCY_TTL_SECONDS', 604800),
        // Legacy install-only: copied into Profile / Family Members by DatabaseSeeder when empty.
        // Bot allowlist and outbound alerts now use users.phone + family_members.
        'personal_number' => env('PERSONAL_WHATSAPP_NUMBER'),
        'personal_extra_numbers' => env('PERSONAL_WHATSAPP_EXTRA_NUMBERS'),
        // Seconds to wait after the last saved WhatsApp receipt before sending a batched "Document received" ack.
        'document_received_debounce_seconds' => (int) env('WHATSAPP_DOCUMENT_RECEIVED_DEBOUNCE_SECONDS', 3),
        // Base URL for WhatsApp deep links (file + expense edit). Use a LAN/public host phones can open.
        // When empty and APP_URL is localhost, tido tries the machine LAN IPv4 automatically.
        'public_app_url' => env('WHATSAPP_PUBLIC_APP_URL'),
        // Intended WhatsApp Linked Devices label (must match Evolution CONFIG_SESSION_PHONE_CLIENT).
        'device_label' => env('CONFIG_SESSION_PHONE_CLIENT', 'tido App (Evolution API)'),
        'login_dev_otp' => env('WHATSAPP_LOGIN_DEV_OTP'),
        'login_dev_phones' => env('WHATSAPP_LOGIN_DEV_PHONES'),
    ],

];
