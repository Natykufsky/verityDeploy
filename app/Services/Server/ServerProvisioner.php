<?php

namespace App\Services\Server;

use App\Models\Server;
use App\Models\ServerConnectionTest;
use App\Services\Cpanel\CpanelApiClient;
use Throwable;

class ServerProvisioner
{
    public function __construct(
        protected ServerConnector $connector,
        protected CpanelApiClient $cpanelApiClient,
    ) {
    }

    public function preflight(Server $server, ?string $path = null): ServerConnectionTest
    {
        $diskTarget = filled($path)
            ? dirname(rtrim($path, '/')) ?: '/'
            : '/';

        if ($diskTarget === '.' || $diskTarget === '\\') {
            $diskTarget = '/';
        }

        $commands = $server->connection_type === 'cpanel'
            ? ['uapi Tokens list', 'uapi Quota get_quota_info']
            : [
                sprintf('df -Pk %s', escapeshellarg($diskTarget)),
                'php -v',
                'command -v composer >/dev/null && composer --version || echo "Composer not found (optional for bootstrap)."',
                'git --version',
            ];

        $test = ServerConnectionTest::query()->create([
            'server_id' => $server->id,
            'status' => 'running',
            'command' => implode(' && ', $commands),
            'tested_at' => now(),
        ]);

        try {
            if ($server->connection_type === 'cpanel') {
                $result = [
                    'ping' => $this->cpanelApiClient->ping($server),
                    'disk' => $this->cpanelApiClient->request($server, 'Quota', 'get_quota_info'),
                ];
                $output = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'cPanel API OK';

                $server->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                    'metrics' => [
                        'api_status' => 'ok',
                        'disk_info' => $result['disk'],
                        'account' => $server->ssh_user,
                    ],
                ]);

                $test->update([
                    'status' => 'successful',
                    'output' => $output,
                    'exit_code' => 0,
                    'error_message' => null,
                ]);

                return $test->fresh();
            }

            $strategy = $this->connector->strategy($server, 20);
            $output = collect($commands)
                ->map(fn (string $command): string => $strategy->run($command))
                ->implode(PHP_EOL.PHP_EOL);

            $server->update([
                'status' => 'online',
                'last_connected_at' => now(),
            ]);

            $test->update([
                'status' => 'successful',
                'output' => $output,
                'exit_code' => 0,
                'error_message' => null,
            ]);

            return $test->fresh();
        } catch (Throwable $throwable) {
            $server->update([
                'status' => $this->isTimeout($throwable) ? 'offline' : 'error',
            ]);

            $test->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    protected function isTimeout(Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());

        return str_contains($message, 'timed out') || str_contains($message, 'timeout');
    }
}
