<?php

namespace App\Services\ServerMetrics;

use App\Models\Server;

class ServerMetricsBridgeAuth
{
    public function make(Server $server): string
    {
        return hash_hmac('sha256', 'server-metrics:'.$server->id, $this->secret());
    }

    public function validate(Server $server, string $token): bool
    {
        return hash_equals($this->make($server), $token);
    }

    protected function secret(): string
    {
        return hash('sha256', (string) config('app.key').'|server-metrics');
    }
}
