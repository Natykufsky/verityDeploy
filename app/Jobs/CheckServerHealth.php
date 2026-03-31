<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerHealthCheck;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Server\ServerConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class CheckServerHealth implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 15;

    public function __construct(
        public int $serverId,
    ) {
    }

    public function handle(ServerConnector $connector, CpanelApiClient $cpanelApiClient): void
    {
        $server = Server::query()->findOrFail($this->serverId);
        $previousStatus = $server->status;
        $check = ServerHealthCheck::query()->create([
            'server_id' => $server->id,
            'status' => 'running',
            'tested_at' => now(),
        ]);

        try {
            if ($server->connection_type === 'cpanel') {
                $payload = [
                    'ping' => $cpanelApiClient->ping($server),
                    'disk' => $cpanelApiClient->request($server, 'Quota', 'get_quota_info'),
                ];

                $metrics = [
                    'api_status' => 'ok',
                    'account' => $server->ssh_user,
                    'token_scope' => 'valid',
                    'disk_info' => $payload['disk'],
                ];

                $server->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                    'metrics' => $metrics,
                ]);

                $check->update([
                    'status' => 'successful',
                    'output' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'cPanel API OK',
                    'metrics' => $metrics,
                    'exit_code' => 0,
                    'error_message' => null,
                ]);

                return;
            }

            $strategy = $connector->strategy($server, 20);
            $uptime = $strategy->run('uptime');
            $free = $strategy->run('free -m');
            $disk = $strategy->run('df -h /');

            $metrics = $this->parseMetrics($uptime, $free, $disk);
            $output = implode(PHP_EOL.PHP_EOL, [
                "uptime:\n".$uptime,
                "free -m:\n".$free,
                "df -h /:\n".$disk,
            ]);

            $server->update([
                'status' => 'online',
                'last_connected_at' => now(),
                'metrics' => $metrics,
            ]);

            $check->update([
                'status' => 'successful',
                'output' => $output,
                'metrics' => $metrics,
                'exit_code' => 0,
                'error_message' => null,
            ]);
        } catch (Throwable $throwable) {
            $status = $this->isTimeout($throwable) ? 'offline' : 'error';

            $server->update([
                'status' => $status,
            ]);

            $check->update([
                'status' => 'failed',
                'output' => $check->output,
                'error_message' => $throwable->getMessage(),
            ]);

            if ($previousStatus !== $status) {
                app(OperationalAlertService::class)->serverUnhealthy(
                    $server->fresh(),
                    $throwable->getMessage(),
                );
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseMetrics(string $uptime, string $free, string $disk): array
    {
        return [
            'cpu_usage' => $this->parseCpuUsage($uptime),
            'ram_usage' => $this->parseRamUsage($free),
            'disk_free' => $this->parseDiskFree($disk),
            'uptime' => $this->parseUptime($uptime),
        ];
    }

    protected function parseCpuUsage(string $uptime): ?float
    {
        if (! preg_match('/load average: ([0-9.]+)/', $uptime, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    protected function parseRamUsage(string $free): ?string
    {
        foreach (preg_split('/\R/', $free) ?: [] as $line) {
            if (! str_starts_with(trim($line), 'Mem:')) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (! is_array($parts) || count($parts) < 3) {
                continue;
            }

            $total = (float) ($parts[1] ?? 0);
            $used = (float) ($parts[2] ?? 0);

            if ($total <= 0) {
                return null;
            }

            return round(($used / $total) * 100, 1) . '%';
        }

        return null;
    }

    protected function parseDiskFree(string $disk): ?string
    {
        foreach (preg_split('/\R/', $disk) ?: [] as $line) {
            if (! preg_match('/\s\/$/', trim($line))) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (! is_array($parts) || count($parts) < 5) {
                continue;
            }

            $usePercent = (string) ($parts[4] ?? '');

            if ($usePercent !== '' && str_ends_with($usePercent, '%')) {
                return (string) (100 - (int) rtrim($usePercent, '%')) . '%';
            }
        }

        return null;
    }

    protected function parseUptime(string $uptime): ?string
    {
        if (preg_match('/up\s+(.*?),\s+\d+\s+users?,\s+load average:/', $uptime, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/up\s+(.*?),\s+load average:/', $uptime, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function isTimeout(Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());

        return str_contains($message, 'timed out') || str_contains($message, 'timeout');
    }
}
