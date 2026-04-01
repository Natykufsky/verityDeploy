<?php

namespace App\Actions;

use App\Jobs\DeployJob;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Deployment\CpanelDeploymentRunner;
use App\Services\Deployment\FileTransportService;
use App\Services\Deployment\ReleaseManager;
use App\Services\SSH\SshCommandRunner;
use Throwable;

class DeployProject
{
    public function __construct(
        protected SshCommandRunner $sshCommandRunner,
        protected FileTransportService $fileTransportService,
        protected CpanelDeploymentRunner $cpanelDeploymentRunner,
        protected ReleaseManager $releaseManager,
    ) {}

    public function dispatch(
        Site $site,
        ?User $user = null,
        string $source = 'manual',
        ?string $commitHash = null,
        ?string $branch = null,
    ): Deployment {
        $deployment = Deployment::create([
            'site_id' => $site->id,
            'triggered_by_user_id' => $user?->id,
            'source' => $source,
            'status' => 'pending',
            'branch' => $branch ?? $site->default_branch,
            'commit_hash' => $commitHash,
        ]);

        $deployment->update([
            'release_path' => $this->buildReleasePath($deployment),
        ]);

        DeployJob::dispatch($deployment->id);

        return $deployment;
    }

    public function rollback(
        Deployment $targetDeployment,
        ?User $user = null,
    ): Deployment {
        if (blank($targetDeployment->release_path)) {
            throw new \RuntimeException('The selected deployment does not have a release path to roll back to.');
        }

        $deployment = Deployment::create([
            'site_id' => $targetDeployment->site_id,
            'triggered_by_user_id' => $user?->id,
            'source' => 'rollback',
            'status' => 'pending',
            'branch' => $targetDeployment->branch,
            'commit_hash' => $targetDeployment->commit_hash,
            'release_path' => $targetDeployment->release_path,
        ]);

        DeployJob::dispatch($deployment->id);

        return $deployment;
    }

    public function resume(
        Deployment $deployment,
        ?User $user = null,
    ): Deployment {
        if (! $deployment->isResumable()) {
            throw new \RuntimeException('This deployment does not have enough completed state to resume safely.');
        }

        $deployment->update([
            'triggered_by_user_id' => $user?->id ?? $deployment->triggered_by_user_id,
            'status' => 'pending',
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'error_message' => null,
            'recovery_hint' => null,
        ]);

        DeployJob::dispatch($deployment->id);

        return $deployment;
    }

    public function run(Deployment $deployment): Deployment
    {
        $deployment->loadMissing('site.server', 'steps');
        $deploymentOutput = (string) $deployment->output;
        $existingSteps = $deployment->steps->keyBy('sequence');

        $deployment->update([
            'status' => 'running',
            'started_at' => now(),
            'output' => null,
            'error_message' => null,
            'recovery_hint' => null,
            'exit_code' => null,
        ]);

        $site = $deployment->site;
        $server = $site->server;

        try {
            $this->assertCanDeploy($deployment, $site, $server);
        } catch (Throwable $throwable) {
            $hint = $this->recoveryHint($throwable, $site);

            $deployment->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
                'recovery_hint' => $hint,
                'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    $deploymentOutput,
                    $this->failureBlock('Deployment blocked', $throwable->getMessage(), $hint),
                ]))),
            ]);

            app(OperationalAlertService::class)->deployFailed($deployment, $throwable->getMessage(), $hint);

            throw $throwable;
        }

        if ($server?->connection_type === 'cpanel') {
            return $this->cpanelDeploymentRunner->run($deployment);
        }

        foreach ($this->buildCommands($deployment) as $index => $step) {
            $sequence = $index + 1;
            $existingStep = $existingSteps->get($sequence);

            if ($existingStep?->status === 'successful') {
                $deploymentOutput .= sprintf(
                    '[%s] Skipped (already successful in a previous attempt).'.PHP_EOL,
                    $step['label'],
                );

                $deployment->update([
                    'output' => trim($deploymentOutput),
                ]);

                continue;
            }

            $deploymentStep = $deployment->steps()->create([
                'sequence' => $sequence,
                'label' => $step['label'],
                'command' => $step['command'],
                'status' => 'running',
                'started_at' => now(),
            ]);
            $stepOutput = '';

            try {
                $process = $this->sshCommandRunner->execute($server, $step['command'], function (string $type, string $line) use (&$deploymentOutput, &$stepOutput, $deployment, $deploymentStep, $step): void {
                    $line = rtrim($line);

                    if ($line === '') {
                        return;
                    }

                    $stepOutput .= $line."\n";
                    $deploymentOutput .= sprintf("[%s] %s\n", $step['label'], $line);

                    $deploymentStep->update([
                        'output' => trim($stepOutput),
                    ]);

                    $deployment->update([
                        'output' => trim($deploymentOutput),
                    ]);
                });
                $output = trim($stepOutput ?: $process->getOutput()."\n".$process->getErrorOutput());

                $deploymentStep->update([
                    'status' => $process->isSuccessful() ? 'successful' : 'failed',
                    'output' => $output,
                    'finished_at' => now(),
                    'exit_code' => $process->getExitCode(),
                ]);

                $deployment->update([
                    'output' => trim($deploymentOutput),
                ]);

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException("Deployment step [{$step['label']}] failed.");
                }
            } catch (Throwable $throwable) {
                $hint = $this->recoveryHint($throwable, $site, $step['label']);

                $deploymentStep->update([
                    'status' => 'failed',
                    'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                        (string) $deploymentStep->output,
                        $this->failureBlock($step['label'], $throwable->getMessage(), $hint),
                    ]))),
                    'finished_at' => now(),
                ]);

                $deployment->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $throwable->getMessage(),
                    'recovery_hint' => $hint,
                    'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                        $deploymentOutput,
                        $this->failureBlock($step['label'], $throwable->getMessage(), $hint),
                    ]))),
                ]);

                app(OperationalAlertService::class)->deployFailed($deployment, $throwable->getMessage(), $hint);

                throw $throwable;
            }
        }

        $deployment->site->update([
            'last_deployed_at' => now(),
        ]);

        $deployment->update([
            'status' => 'successful',
            'finished_at' => now(),
            'exit_code' => 0,
        ]);

        return $deployment->fresh(['site.server', 'steps']);
    }

    /**
     * @return array<int, array{label: string, command: string}>
     */
    protected function buildCommands(Deployment $deployment): array
    {
        $site = $deployment->site;
        $basePath = rtrim($site->deploy_path, '/');
        $releasePath = $deployment->release_path ?: $this->buildReleasePath($deployment);
        $currentPath = $basePath.'/current';
        $commands = [];

        if ($deployment->source !== 'rollback') {
            $commands[] = [
                'label' => 'Preflight checks',
                'command' => $site->deploy_source === 'git'
                    ? sprintf(
                        'mkdir -p %s && command -v git >/dev/null && (command -v composer >/dev/null || command -v composer2 >/dev/null || command -v ea-composer >/dev/null || [ -x /opt/cpanel/composer/bin/composer ]) && command -v php >/dev/null',
                        escapeshellarg($basePath),
                    )
                    : sprintf(
                        'mkdir -p %s && command -v tar >/dev/null && (command -v composer >/dev/null || command -v composer2 >/dev/null || command -v ea-composer >/dev/null || [ -x /opt/cpanel/composer/bin/composer ]) && command -v php >/dev/null',
                        escapeshellarg($basePath),
                    ),
            ];

            $commands[] = [
                'label' => 'Prepare release tree',
                'command' => sprintf(
                    'mkdir -p %1$s/releases %1$s/shared && rm -rf %2$s && mkdir -p %2$s',
                    escapeshellarg($basePath),
                    escapeshellarg($releasePath),
                ),
            ];

            $commands = array_merge($commands, $this->fileTransportService->transferCommands($deployment));

            $commands[] = [
                'label' => 'Sync shared files',
                'command' => implode(' && ', $this->releaseManager->syncSharedRuntimeCommands($site)),
            ];

            $commands[] = [
                'label' => 'Link shared files',
                'command' => implode(' && ', $this->releaseManager->linkSharedRuntimeCommands($site, $releasePath)),
            ];
        }

        if ($deployment->source !== 'rollback') {
            $commands[] = [
                'label' => 'Install dependencies',
                'command' => $this->composerInstallCommand($site->deploy_source === 'git' || $site->deploy_source === 'local' ? $releasePath : $basePath),
            ];

            $commands[] = [
                'label' => 'Run migrations',
                'command' => sprintf(
                    'cd %s && php artisan migrate --force',
                    escapeshellarg($site->deploy_source === 'git' || $site->deploy_source === 'local' ? $releasePath : $basePath),
                ),
            ];
        }

        if ($site->deploy_source === 'git' || $site->deploy_source === 'local' || $deployment->source === 'rollback') {
            $commands[] = [
                'label' => 'Activate release',
                'command' => sprintf('ln -sfn %s %s', escapeshellarg($releasePath), escapeshellarg($currentPath)),
            ];

            $commands[] = [
                'label' => 'Restart queue workers',
                'command' => sprintf('cd %s && php artisan queue:restart', escapeshellarg($currentPath)),
            ];
        } else {
            $commands[] = [
                'label' => 'Restart queue workers',
                'command' => sprintf('cd %s && php artisan queue:restart', escapeshellarg($basePath)),
            ];
        }

        return $commands;
    }

    protected function buildReleasePath(Deployment $deployment): string
    {
        $basePath = rtrim($deployment->site->deploy_path, '/');
        $stamp = now()->utc()->format('YmdHis');

        return sprintf('%s/releases/%s-%s', $basePath, $stamp, $deployment->id);
    }

    protected function assertCanDeploy(Deployment $deployment, Site $site, ?Server $server): void
    {
        if (blank($server)) {
            throw new \RuntimeException('The site does not have a server configured.');
        }

        if (blank($site->deploy_path)) {
            throw new \RuntimeException('The site does not have a deploy path configured.');
        }

        if ($site->deploy_source === 'git' && blank($site->repository_url)) {
            throw new \RuntimeException('The site does not have a Git repository configured.');
        }

        if ($site->deploy_source === 'local' && blank($site->local_source_path)) {
            throw new \RuntimeException('The site does not have a local source path configured.');
        }

        if ($server?->connection_type === 'cpanel' && blank($server->cpanel_api_token)) {
            throw new \RuntimeException('The cPanel server does not have an API token configured.');
        }

        if ($deployment->source === 'rollback') {
            if (! in_array($site->deploy_source, ['git', 'local'], true)) {
                throw new \RuntimeException('Rollback is only available for release-based sites.');
            }

            if (blank($deployment->release_path)) {
                throw new \RuntimeException('The rollback deployment is missing a release path.');
            }
        }
    }

    protected function sanitizeShellToken(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._\-\/]/', '', $value) ?: 'main';
    }

    protected function composerInstallCommand(string $workingDirectory): string
    {
        $script = implode(' ; ', [
            'composer_bin=$(command -v composer 2>/dev/null || command -v composer2 2>/dev/null || command -v ea-composer 2>/dev/null || printf %s /opt/cpanel/composer/bin/composer)',
            'if [ ! -x "$composer_bin" ]; then echo "Composer not found on the remote server. Install Composer or expose composer2 / /opt/cpanel/composer/bin/composer in PATH."; exit 127; fi',
            sprintf('cd %s && "$composer_bin" install --no-interaction --prefer-dist --no-dev --optimize-autoloader', $this->shellDoubleQuoteArgument($workingDirectory)),
        ]);

        return $script;
    }

    protected function shellDoubleQuoteArgument(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$', '`'], ['\\\\', '\\"', '\$', '\`'], $value).'"';
    }

    protected function recoveryHint(Throwable $throwable, Site $site, ?string $step = null): string
    {
        $message = strtolower($throwable->getMessage());

        return match (true) {
            str_contains($message, 'deploy path') => 'Double-check the deploy path and make sure the target directory exists or can be created.',
            str_contains($message, 'repository') => 'Verify the repository URL, branch, and access token or SSH key before retrying.',
            str_contains($message, 'local source path') => 'Confirm the local source path exists on the dashboard machine and is readable.',
            str_contains($message, 'cpanel') && str_contains($message, 'token') => 'Re-run the cPanel setup wizard to refresh the API token and SSH port, then retry.',
            str_contains($message, 'timed out') || str_contains($message, 'timeout') => 'Check network reachability, SSH port, and host firewall rules before retrying.',
            str_contains($message, 'permission denied') => 'Verify file permissions, SSH credentials, and any required sudo access.',
            default => filled($step)
                ? sprintf('Review the %s step output, fix the underlying issue, and retry the deployment.', $step)
                : sprintf('Review the site configuration for %s and retry once the blocking issue is fixed.', $site->name),
        };
    }

    protected function failureBlock(string $stage, string $message, string $hint): string
    {
        return implode(PHP_EOL, [
            sprintf('[%s] FAILED', $stage),
            $message,
            sprintf('Recovery: %s', $hint),
        ]);
    }
}
