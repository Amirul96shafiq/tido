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
        'host' => env('OLLAMA_HOST', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'minicpm-v'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    ],

    'evolution' => [
        'api_url' => env('EVOLUTION_API_URL', 'http://evolution-api:8080'),
        'api_key' => env('EVOLUTION_API_KEY', 'trackall-secret-key'),
        'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'trackall'),
        'personal_number' => env('PERSONAL_WHATSAPP_NUMBER'),
    ],

];
