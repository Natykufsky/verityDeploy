<?php

namespace App\Services\Processes;

use App\Models\Site;
use App\Models\SiteTerminalRun;
use App\Models\User;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SiteProcessService
{
    public function __construct(
        protected SshCommandRunner $sshCommandRunner,
    ) {}

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
}
