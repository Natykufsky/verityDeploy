<?php

namespace App\Services\Server\Connections;

use App\Models\Server;
use App\Services\Cpanel\CpanelApiClient;
use RuntimeException;

class CpanelStrategy implements ConnectionStrategy
{
    public function __construct(
        protected Server $server,
        protected int $timeout = 0,
        protected ?CpanelApiClient $client = null,
    ) {
        $this->client ??= app(CpanelApiClient::class);
    }

    public function run(string $command): string
    {
        $normalized = trim(strtolower($command));

        return match ($normalized) {
            'whoami' => trim((string) $this->server->ssh_user),
            'ping', 'api ping', 'cpanel ping' => $this->ping(),
            default => throw new RuntimeException('cPanel connections support API-based checks only.'),
        };
    }

    protected function ping(): string
    {
        $this->client->ping($this->server);

        return 'cPanel API OK';
    }
}
