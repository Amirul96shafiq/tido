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
    ],

    'evolution' => [
        'api_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8080'),
        'api_key' => env('EVOLUTION_API_KEY', 'tido-secret-key'),
        'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'tido'),
        // Primary: panel access, OTP, outbound alerts/ping (single number).
        'personal_number' => env('PERSONAL_WHATSAPP_NUMBER'),
        // Extra senders allowed to talk to the bot / upload receipts (comma/space/semicolon separated).
        // Does not grant panel or OTP login.
        'personal_extra_numbers' => env('PERSONAL_WHATSAPP_EXTRA_NUMBERS'),
        // Seconds to wait after the last saved WhatsApp receipt before sending a batched "Document received" ack.
        'document_received_debounce_seconds' => (int) env('WHATSAPP_DOCUMENT_RECEIVED_DEBOUNCE_SECONDS', 3),
        // Base URL for WhatsApp deep links (file + invoice edit). Use a LAN/public host phones can open.
        // When empty and APP_URL is localhost, tido tries the machine LAN IPv4 automatically.
        'public_app_url' => env('WHATSAPP_PUBLIC_APP_URL'),
        // Intended WhatsApp Linked Devices label (must match Evolution CONFIG_SESSION_PHONE_CLIENT).
        'device_label' => env('CONFIG_SESSION_PHONE_CLIENT', 'tido App (Evolution API)'),
    ],

];
