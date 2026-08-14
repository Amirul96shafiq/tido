<?php

declare(strict_types=1);

/**
 * Echo client config for the Filament panel only.
 *
 * Panel settings stay in AdminPanelProvider. Do not add panel/theme keys here.
 *
 * @see docs/realtime-broadcasting.md
 */
return [
    'broadcasting' => [
        'echo' => [
            'broadcaster' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'cluster' => '',
            'wsHost' => env('REVERB_HOST', 'localhost'),
            'wsPort' => env('REVERB_PORT', 8081),
            'wssPort' => env('REVERB_PORT', 8081),
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
            'encrypted' => true,
            'forceTLS' => env('REVERB_SCHEME', 'http') === 'https',
            'enabledTransports' => ['ws', 'wss'],
        ],
    ],
];
