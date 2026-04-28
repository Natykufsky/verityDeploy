<?php

namespace App\Services\Processes;

use App\Models\Site;
use App\Models\SiteTerminalRun;
use App\Models\User;
use App\Services\Alerts\OperationalAlertService;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SiteProcessService
{
    public function __construct(
        protected SshCommandRunner $sshCommandRunner,
        protected ?OperationalAlertService $alerts = null,
    ) {
        $this->alerts ??= app(OperationalAlertService::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $site->loadMissing('server');
        $releasePath = $this->releasePath($site);

        return [
            'supported' => filled($site->server) && filled($releasePath),
            'release_path' => $releasePath,
            'actions' => [
                [
                    'key' => 'queue_restart',
                    'label' => 'Restart queue workers',
                    'description' => 'Signals Laravel queue workers to gracefully restart.',
                    'command' => filled($releasePath) ? $this->queueRestartCommand($site) : null,
                ],
                [
                    'key' => 'horizon_terminate',
                    'label' => 'Terminate Horizon',
                    'description' => 'Stops Horizon so your supervisor or daemon can start it again.',
                    'command' => filled($releasePath) ? $this->horizonTerminateCommand($site) : null,
                ],
                [
                    'key' => 'supervisor_restart',
                    'label' => 'Restart supervisor',
                    'description' => 'Restarts the process supervisor that keeps queue workers alive.',
                    'command' => filled($releasePath) ? $this->supervisorRestartCommand($site) : null,
                ],
                [
                    'key' => 'daemon_status',
                    'label' => 'Check daemon status',
                    'description' => 'Checks supervisor, Horizon, and queue workers so you can confirm background daemons are alive.',
                    'command' => filled($releasePath) ? $this->daemonStatusCommand($site) : null,
                ],
                [
                    'key' => 'daemon_recover',
                    'label' => 'Recover daemon stack',
                    'description' => 'Attempts to restart supervisor, Horizon, and queue workers in one pass.',
                    'command' => filled($releasePath) ? $this->daemonRecoveryCommand($site) : null,
                ],
                [
                    'key' => 'daemon_cycle',
                    'label' => 'Restart and recover',
                    'description' => 'Checks the daemon stack first, then runs the recovery pass if the status suggests a problem.',
                    'command' => filled($releasePath) ? $this->daemonCycleCommand($site) : null,
                ],
            ],
        ];
    }

    public function run(Site $site, string $action, ?User $user = null): SiteTerminalRun
    {
        $site->loadMissing('server');

        if (! $site->server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        $commands = $this->commandsForAction($site, $action);
        $label = $this->labelForAction($action);
        $commandLine = implode(' && ', $commands);

        $run = SiteTerminalRun::query()->create([
            'site_id' => $site->id,
            'user_id' => $user?->id,
            'command' => sprintf('%s:%s', $action, $commandLine),
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $this->sshCommandRunner->run($site->server, $commands);
            $output = trim((string) ($result['output'] ?? ''));
            $exitCode = (int) ($result['exit_code'] ?? 0);
            $status = $exitCode === 0 ? 'successful' : 'failed';

            $run->update([
                'status' => $status,
                'output' => $output,
                'exit_code' => $exitCode,
                'finished_at' => now(),
                'error_message' => $status === 'failed' ? ($output !== '' ? $output : 'The process command returned a non-zero exit code.') : null,
            ]);

            if ($status === 'successful' && $action === 'daemon_status' && $this->daemonStatusHasWarnings($output)) {
                $this->alerts->notifyAll(
                    "Daemon issue detected: {$site->name}",
                    $this->daemonStatusSummary($output),
                    'warning',
                    null,
                    [
                        'site_id' => $site->id,
                        'site_name' => $site->name,
                        'server_id' => $site->server->id,
                        'action' => $action,
                    ],
                );
            }

            if ($action === 'daemon_recover') {
                if ($status === 'successful') {
                    $this->alerts->siteDaemonRecovered(
                        $site->fresh(),
                        $this->daemonStatusSummary($output),
                    );
                } else {
                    $this->alerts->siteDaemonRecoveryFailed(
                        $site->fresh(),
                        $this->daemonStatusSummary($output !== '' ? $output : 'The daemon recovery command failed before it produced output.'),
                    );
                }
            }

            if ($action === 'daemon_cycle') {
                if ($status === 'successful') {
                    $this->alerts->siteDaemonRecovered(
                        $site->fresh(),
                        $this->daemonStatusSummary($output),
                    );
                } else {
                    $this->alerts->siteDaemonRecoveryFailed(
                        $site->fresh(),
                        $this->daemonStatusSummary($output !== '' ? $output : 'The daemon cycle command failed before it produced output.'),
                    );
                }
            }
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'output' => null,
                'exit_code' => 1,
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $run->fresh(['site.server', 'user']);
    }

    /**
     * @return array<int, string>
     */
    protected function commandsForAction(Site $site, string $action): array
    {
        $releasePath = $this->releasePath($site);

        if (blank($releasePath)) {
            throw new RuntimeException('The site does not have a current release path configured.');
        }

        return match ($action) {
            'queue_restart' => [
                sprintf('cd %s', escapeshellarg($releasePath)),
                'php artisan queue:restart',
            ],
            'horizon_terminate' => [
                sprintf('cd %s', escapeshellarg($releasePath)),
                'php artisan horizon:terminate',
            ],
            'supervisor_restart' => [
                'supervisorctl restart all',
            ],
            'daemon_status' => [
                $this->daemonStatusCommand($site),
            ],
            'daemon_recover' => [
                $this->daemonRecoveryCommand($site),
            ],
            'daemon_cycle' => [
                $this->daemonCycleCommand($site),
            ],
            default => throw new RuntimeException('Unknown process action requested.'),
        };
    }

    protected function releasePath(Site $site): ?string
    {
        if (filled($site->current_release_path)) {
            $releasePath = rtrim((string) $site->current_release_path, '/');

            return filled($releasePath) ? $releasePath : null;
        }

        if (blank($site->deploy_path)) {
            return null;
        }

        $releasePath = rtrim((string) $site->deploy_path, '/').'/current';

        return filled($releasePath) ? $releasePath : null;
    }

    protected function labelForAction(string $action): string
    {
        return match ($action) {
            'queue_restart' => 'Restart queue workers',
            'horizon_terminate' => 'Terminate Horizon',
            'supervisor_restart' => 'Restart supervisor',
            'daemon_status' => 'Check daemon status',
            'daemon_recover' => 'Recover daemon stack',
            'daemon_cycle' => 'Restart and recover',
            default => Str::headline(str_replace('_', ' ', $action)),
        };
    }

    protected function queueRestartCommand(Site $site): string
    {
        return implode(PHP_EOL, $this->commandsForAction($site, 'queue_restart'));
    }

    protected function horizonTerminateCommand(Site $site): string
    {
        return implode(PHP_EOL, $this->commandsForAction($site, 'horizon_terminate'));
    }

    protected function supervisorRestartCommand(Site $site): string
    {
        return implode(PHP_EOL, $this->commandsForAction($site, 'supervisor_restart'));
    }

    protected function daemonStatusCommand(Site $site): string
    {
        $releasePath = $this->releasePath($site);

        if (blank($releasePath)) {
            throw new RuntimeException('The site does not have a current release path configured.');
        }

        return implode(PHP_EOL, [
            sprintf('cd %s', escapeshellarg($releasePath)),
            <<<'BASH'
set +e
echo "== supervisor =="
if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl status
else
    echo "supervisorctl not installed"
fi
echo "== horizon =="
if php artisan horizon:status >/dev/null 2>&1; then
    php artisan horizon:status
else
    echo "horizon not configured or unavailable"
fi
echo "== queue workers =="
if pgrep -af "artisan queue:work" >/dev/null 2>&1; then
    pgrep -af "artisan queue:work"
else
    echo "queue workers not detected"
fi
BASH,
        ]);
    }

    protected function daemonRecoveryCommand(Site $site): string
    {
        $releasePath = $this->releasePath($site);

        if (blank($releasePath)) {
            throw new RuntimeException('The site does not have a current release path configured.');
        }

        return implode(PHP_EOL, [
            sprintf('cd %s', escapeshellarg($releasePath)),
            <<<'BASH'
set +e
errors=0

echo "== supervisor =="
if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart all
    supervisor_exit=$?
    if [ "$supervisor_exit" -ne 0 ]; then
        errors=$((errors + 1))
    fi
else
    echo "supervisorctl not installed"
fi

echo "== horizon =="
if php artisan horizon:status >/dev/null 2>&1; then
    php artisan horizon:terminate
    horizon_exit=$?
    if [ "$horizon_exit" -ne 0 ]; then
        errors=$((errors + 1))
    fi
else
    echo "horizon not configured or unavailable"
fi

echo "== queue workers =="
php artisan queue:restart
queue_exit=$?
if [ "$queue_exit" -ne 0 ]; then
    errors=$((errors + 1))
fi

if [ "$errors" -ne 0 ]; then
    echo "daemon recovery finished with ${errors} error(s)"
fi

exit "$errors"
BASH,
        ]);
    }

    protected function daemonCycleCommand(Site $site): string
    {
        $releasePath = $this->releasePath($site);

        if (blank($releasePath)) {
            throw new RuntimeException('The site does not have a current release path configured.');
        }

        return implode(PHP_EOL, [
            sprintf('cd %s', escapeshellarg($releasePath)),
            <<<'BASH'
set +e
echo "== daemon status =="
supervisor_ok=0
horizon_ok=0
queue_ok=0

if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl status || supervisor_ok=1
else
    echo "supervisorctl not installed"
    supervisor_ok=1
fi

if php artisan horizon:status >/dev/null 2>&1; then
    php artisan horizon:status
else
    echo "horizon not configured or unavailable"
    horizon_ok=1
fi

if pgrep -af "artisan queue:work" >/dev/null 2>&1; then
    pgrep -af "artisan queue:work"
else
    echo "queue workers not detected"
    queue_ok=1
fi

if [ "$supervisor_ok" -eq 0 ] && [ "$horizon_ok" -eq 0 ] && [ "$queue_ok" -eq 0 ]; then
    echo "daemon stack healthy"
    exit 0
fi

echo "== daemon recovery =="
errors=0

if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart all || errors=$((errors + 1))
fi

if php artisan horizon:status >/dev/null 2>&1; then
    php artisan horizon:terminate || errors=$((errors + 1))
fi

php artisan queue:restart || errors=$((errors + 1))

if [ "$errors" -ne 0 ]; then
    echo "daemon cycle finished with ${errors} recovery error(s)"
fi

exit "$errors"
BASH,
        ]);
    }

    protected function daemonStatusHasWarnings(string $output): bool
    {
        $output = strtolower($output);

        return str_contains($output, 'not installed')
            || str_contains($output, 'not configured')
            || str_contains($output, 'not detected')
            || str_contains($output, 'failed');
    }

    protected function daemonStatusSummary(string $output): string
    {
        $lines = Collection::make(preg_split('/\R/', trim($output)) ?: [])
            ->filter(fn ($line) => filled(trim((string) $line)))
            ->take(6)
            ->values()
            ->all();

        if ($lines === []) {
            return 'The daemon status check completed, but no output was returned.';
        }

        return implode(' ', $lines);
    }
}
