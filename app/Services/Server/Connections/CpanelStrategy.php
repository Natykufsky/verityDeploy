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
        return $this->streamRun($command);
    }

    public function streamRun(string $command, ?callable $onOutput = null): string
    {
        $normalized = trim(strtolower($command));

        $result = match ($normalized) {
            'whoami' => trim((string) $this->server->ssh_user),
            'ping', 'api ping', 'cpanel ping' => $this->ping(),
            default => throw new RuntimeException('cPanel connections support API-based checks only.'),
        };

        if ($onOutput) {
            $onOutput('output', $result.PHP_EOL);
        }

        return $result;
    }

    protected function ping(): string
    {
        $this->client->ping($this->server);

        return 'cPanel API OK';
    }
}
