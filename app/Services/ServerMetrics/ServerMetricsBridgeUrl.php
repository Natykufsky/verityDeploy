<?php

namespace App\Services\ServerMetrics;

use App\Models\Server;

class ServerMetricsBridgeUrl
{
    public function __construct(
        protected ServerMetricsBridgeAuth $auth,
    ) {}

    public function make(Server $server): array
    {
        $bridge = config('server_metrics.bridge', []);

        if (! ($bridge['enabled'] ?? false)) {
            return [
                'enabled' => false,
                'url' => null,
            ];
        }

        return [
            'enabled' => true,
            'url' => sprintf(
                '%s://%s:%d?server_id=%d&token=%s',
                $bridge['scheme'] ?? 'ws',
                $bridge['host'] ?? '127.0.0.1',
                (int) ($bridge['port'] ?? 8788),
                $server->id,
                $this->auth->make($server),
            ),
        ];
    }
}
