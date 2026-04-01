<?php

return [
    'bridge' => [
        'enabled' => env('TERMINAL_BRIDGE_ENABLED', true),
        'host' => env('TERMINAL_BRIDGE_HOST', '127.0.0.1'),
        'port' => (int) env('TERMINAL_BRIDGE_PORT', 8787),
        'scheme' => env('TERMINAL_BRIDGE_SCHEME', 'ws'),
    ],
];
