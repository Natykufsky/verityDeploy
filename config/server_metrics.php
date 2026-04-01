<?php

return [
    'bridge' => [
        'enabled' => env('SERVER_METRICS_BRIDGE_ENABLED', true),
        'host' => env('SERVER_METRICS_BRIDGE_HOST', '127.0.0.1'),
        'port' => (int) env('SERVER_METRICS_BRIDGE_PORT', 8788),
        'scheme' => env('SERVER_METRICS_BRIDGE_SCHEME', 'ws'),
        'poll_interval' => (int) env('SERVER_METRICS_BRIDGE_POLL_INTERVAL', 5),
    ],
];
