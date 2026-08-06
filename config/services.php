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
        // Ollama silently defaults to 4096 regardless of the model's advertised context.
        // Raising this grows the KV cache, so only increase it when the GPU has spare VRAM.
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 4096),
        // Long-edge cap for vision prompts; qwen2.5vl spends ~1 token per 28x28 pixel block,
        // so full-resolution uploads can fill num_ctx and leave no budget for the JSON answer.
        'max_image_dimension' => (int) env('OLLAMA_MAX_IMAGE_DIMENSION', 1280),
    ],

    'documents' => [
        'max_bytes' => (int) env('PDF_MAX_BYTES', 10 * 1024 * 1024),
        'max_pdf_pages' => (int) env('PDF_MAX_PAGES', 3),
        'pdfinfo_binary' => env('PDFINFO_BINARY', 'pdfinfo'),
        'pdftocairo_binary' => env('PDFTOCAIRO_BINARY', 'pdftocairo'),
        'pdf_inspection_timeout' => (int) env('PDF_INSPECTION_TIMEOUT', 15),
        'pdf_render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 60),
        'pdf_render_dpi' => (int) env('PDF_RENDER_DPI', 144),
    ],

    'evolution' => [
        'api_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8080'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'tido'),
        // Legacy install-only: copied into Profile / Family Members by DatabaseSeeder when empty.
        // Bot allowlist and outbound alerts now use users.phone + family_members.
        'personal_number' => env('PERSONAL_WHATSAPP_NUMBER'),
        'personal_extra_numbers' => env('PERSONAL_WHATSAPP_EXTRA_NUMBERS'),
        // Seconds to wait after the last saved WhatsApp receipt before sending a batched "Document received" ack.
        'document_received_debounce_seconds' => (int) env('WHATSAPP_DOCUMENT_RECEIVED_DEBOUNCE_SECONDS', 3),
        // Base URL for WhatsApp deep links (file + invoice edit). Use a LAN/public host phones can open.
        // When empty and APP_URL is localhost, tido tries the machine LAN IPv4 automatically.
        'public_app_url' => env('WHATSAPP_PUBLIC_APP_URL'),
        // Intended WhatsApp Linked Devices label (must match Evolution CONFIG_SESSION_PHONE_CLIENT).
        'device_label' => env('CONFIG_SESSION_PHONE_CLIENT', 'tido App (Evolution API)'),
        'login_dev_otp' => env('WHATSAPP_LOGIN_DEV_OTP'),
        'login_dev_phones' => env('WHATSAPP_LOGIN_DEV_PHONES'),
    ],

];
