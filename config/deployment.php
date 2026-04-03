<?php

return [
    'bridge' => [
        'enabled' => (bool) env('DEPLOYMENT_BRIDGE_ENABLED', true),
        'host' => env('DEPLOYMENT_BRIDGE_HOST', '127.0.0.1'),
        'port' => (int) env('DEPLOYMENT_BRIDGE_PORT', 8789),
        'scheme' => env('DEPLOYMENT_BRIDGE_SCHEME', 'ws'),
        'poll_interval' => (int) env('DEPLOYMENT_BRIDGE_POLL_INTERVAL', 2),
    ],
];
