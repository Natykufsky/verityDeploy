<?php

namespace App\Services\Server;

use App\Models\Server;
use App\Services\Server\Connections\ConnectionStrategy;
use App\Services\Server\Connections\CpanelStrategy;
use App\Services\Server\Connections\LocalStrategy;
use App\Services\Server\Connections\SshKeyStrategy;
use App\Services\Server\Connections\SshPasswordStrategy;

class ServerConnector
{
    public function strategy(Server $server, int $timeout = 0): ConnectionStrategy
    {
        return match ($server->connection_type) {
            'local' => app(LocalStrategy::class, ['timeout' => $timeout]),
            'password' => app(SshPasswordStrategy::class, ['server' => $server, 'timeout' => $timeout]),
            'cpanel' => app(CpanelStrategy::class, ['server' => $server, 'timeout' => $timeout]),
            default => app(SshKeyStrategy::class, ['server' => $server, 'timeout' => $timeout]),
        };
    }
}
