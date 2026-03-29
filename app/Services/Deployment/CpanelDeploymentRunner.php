<?php

namespace App\Services\Deployment;

use App\Models\Deployment;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelSiteProvisioner;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class CpanelDeploymentRunner
{
    public function __construct(
        protected CpanelApiClient $client,
        protected CpanelSiteProvisioner $siteProvisioner,
        protected FileTransportService $fileTransportService,
        protected SshCommandRunner $sshCommandRunner,
    ) {
    }

    public function run(Deployment $deployment): Deployment
    {
        $deployment->loadMissing('site.server', 'steps');
        $site = $deployment->site;

        try {
            if (! in_array($site->deploy_source, ['git', 'local'], true)) {
                throw new RuntimeException('cPanel deployments currently support Git and local-source sites only.');
            }

            if ($site->deploy_source === 'git' && blank($site->repository_url)) {
                throw new RuntimeException('The site does not have a Git repository configured.');
            }

            if ($site->deploy_source === 'local' && blank($site->local_source_path)) {
                throw new RuntimeException('The site does not have a local source path configured.');
            }

            $deployment->update([
                'status' => 'running',
                'started_at' => now(),
                'output' => null,
                'error_message' => null,
                'recovery_hint' => null,
                'exit_code' => null,
                'release_path' => $deployment->release_path ?: $this->siteProvisioner->buildReleasePath($deployment),
            ]);

            if ($deployment->source === 'rollback') {
                return $this->runRollback($deployment);
            }

            $steps = $site->deploy_source === 'local'
                ? $this->localSourceSteps($deployment)
                : $this->gitSourceSteps($deployment);

            return $this->executeSteps($deployment, $steps, fn () => $deployment->site->update([
                'last_deployed_at' => now(),
                'current_release_path' => $deployment->release_path,
            ]));
        } catch (Throwable $throwable) {
            $hint = $this->recoveryHint($throwable, $deployment);

            $deployment->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
                'recovery_hint' => $hint,
                'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    (string) $deployment->output,
                    $this->failureBlock('cPanel deployment', $throwable->getMessage(), $hint),
                ]))),
            ]);

            app(OperationalAlertService::class)->deployFailed($deployment, $throwable->getMessage(), $hint);

            throw $throwable;
        }
    }

    public function rollback(Deployment $deployment): Deployment
    {
        return $this->run($deployment);
    }

    /**
     * @return array<int, array{label: string, command: string, runner: callable(): array<string, mixed>|string}>
     */
    protected function gitSourceSteps(Deployment $deployment): array
    {
        $site = $deployment->site;
        $releasePath = (string) $deployment->release_path;

        return [
            [
                'label' => 'Validate cPanel API',
                'command' => 'uapi Tokens list',
                'runner' => fn (): array => $this->client->ping($site->server),
            ],
            [
                'label' => 'Provision workspace',
                'command' => 'mkdir -p shared releases',
                'runner' => function () use ($site): array {
                    $this->siteProvisioner->ensureWorkspace($site);

                    return ['summary' => 'Workspace directories ensured.'];
                },
            ],
            [
                'label' => 'Prepare release directory',
                'command' => 'mkdir -p release',
                'runner' => function () use ($site, $releasePath): array {
                    $this->siteProvisioner->ensureReleaseDirectory($site, $releasePath);

                    return ['release_path' => $releasePath];
                },
            ],
            [
                'label' => 'Ensure repository',
                'command' => 'uapi VersionControl create',
                'runner' => fn (): array => $this->client->ensureRepository(
                    $site->server,
                    $releasePath,
                    $site->name,
                    $site->repository_url,
                ),
            ],
            [
                'label' => 'Set active branch',
                'command' => 'uapi VersionControl update',
                'runner' => fn (): array => $this->client->updateRepository(
                    $site->server,
                    $releasePath,
                    $deployment->branch ?? $site->default_branch,
                    $site->name,
                ),
            ],
            [
                'label' => 'Create deployment task',
                'command' => 'uapi VersionControlDeployment create',
                'runner' => fn (): array => $this->client->createDeployment($site->server, $releasePath),
            ],
            [
                'label' => 'Sync shared runtime',
                'command' => 'shared runtime sync',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->syncSharedRuntime($site, $releasePath),
                ],
            ],
            [
                'label' => 'Activate release',
                'command' => 'ln -sfn release current',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->activateRelease($site, $releasePath),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, command: string, runner: callable(): array<string, mixed>|string}>
     */
    protected function localSourceSteps(Deployment $deployment): array
    {
        $site = $deployment->site;
        $releasePath = (string) $deployment->release_path;
        $transferCommands = [];

        return [
            [
                'label' => 'Validate cPanel API',
                'command' => 'uapi Tokens list',
                'runner' => fn (): array => $this->client->ping($site->server),
            ],
            [
                'label' => 'Provision workspace',
                'command' => 'mkdir -p shared releases',
                'runner' => function () use ($site): array {
                    $this->siteProvisioner->ensureWorkspace($site);

                    return ['summary' => 'Workspace directories ensured.'];
                },
            ],
            [
                'label' => 'Prepare release directory',
                'command' => 'mkdir -p release',
                'runner' => function () use ($site, $releasePath): array {
                    $this->siteProvisioner->ensureReleaseDirectory($site, $releasePath);

                    return ['release_path' => $releasePath];
                },
            ],
            [
                'label' => 'Transfer local source archive',
                'command' => 'scp source.tar.gz remote',
                'runner' => function () use ($deployment, &$transferCommands): array {
                    $transferCommands = $this->fileTransportService->transferCommands($deployment);

                    return [
                        'summary' => 'Archive packaged and uploaded via SSH.',
                        'commands' => $transferCommands,
                    ];
                },
            ],
            [
                'label' => 'Extract source archive',
                'command' => 'tar -xzf archive -C release',
                'runner' => function () use ($site, $releasePath, &$transferCommands): array {
                    $extractCommand = data_get($transferCommands, '0.command');

                    if (blank($extractCommand)) {
                        throw new RuntimeException('The local source archive transfer did not prepare an extraction command.');
                    }

                    $process = $this->sshCommandRunner->execute($site->server, $extractCommand);

                    if (! $process->isSuccessful()) {
                        throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Unable to extract the uploaded archive.');
                    }

                    return [
                        'summary' => 'Archive extracted into the release directory.',
                        'release_path' => $releasePath,
                    ];
                },
            ],
            [
                'label' => 'Sync shared runtime',
                'command' => 'shared runtime sync',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->syncSharedRuntime($site, $releasePath),
                ],
            ],
            [
                'label' => 'Activate release',
                'command' => 'ln -sfn release current',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->activateRelease($site, $releasePath),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, command: string, runner: callable(): array<string, mixed>|string}>  $steps
     */
    protected function executeSteps(Deployment $deployment, array $steps, ?callable $afterSuccess = null): Deployment
    {
        $deploymentOutput = (string) $deployment->output;

        try {
            foreach ($steps as $index => $step) {
                $deploymentStep = $deployment->steps()->create([
                    'sequence' => $index + 1,
                    'label' => $step['label'],
                    'command' => $step['command'],
                    'status' => 'running',
                    'started_at' => now(),
                ]);

                $result = ($step['runner'])();
                $stepOutput = is_string($result)
                    ? $result
                    : (json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '');

                $deploymentOutput .= sprintf("[%s] %s\n", $step['label'], $stepOutput)."\n";

                $deploymentStep->update([
                    'status' => 'successful',
                    'output' => $stepOutput,
                    'finished_at' => now(),
                    'exit_code' => 0,
                ]);

                $deployment->update([
                    'output' => trim($deploymentOutput),
                ]);
            }

            if ($afterSuccess) {
                $afterSuccess();
            }

            $deployment->update([
                'status' => 'successful',
                'finished_at' => now(),
                'exit_code' => 0,
                'output' => trim($deploymentOutput),
            ]);

            return $deployment->fresh(['site.server', 'steps']);
        } catch (Throwable $throwable) {
            $hint = $this->recoveryHint($throwable, $deployment, $deploymentStep->label);

            $deploymentStep->update([
                'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    (string) $deploymentStep->output,
                    $this->failureBlock($deploymentStep->label, $throwable->getMessage(), $hint),
                ]))),
            ]);

            $deployment->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
                'recovery_hint' => $hint,
                'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    $deploymentOutput,
                    $this->failureBlock($deploymentStep->label, $throwable->getMessage(), $hint),
                ]))),
            ]);

            app(OperationalAlertService::class)->deployFailed($deployment, $throwable->getMessage(), $hint);

            throw $throwable;
        }
    }

    protected function runRollback(Deployment $deployment): Deployment
    {
        $site = $deployment->site;
        $releasePath = (string) $deployment->release_path;

        if (blank($releasePath)) {
            throw new RuntimeException('The rollback deployment does not have a release path.');
        }

        $steps = [
            [
                'label' => 'Validate cPanel API',
                'command' => 'uapi Tokens list',
                'runner' => fn (): array => $this->client->ping($site->server),
            ],
            [
                'label' => 'Refresh shared runtime',
                'command' => 'shared runtime sync',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->syncSharedRuntime($site, $releasePath),
                ],
            ],
            [
                'label' => 'Activate previous release',
                'command' => 'ln -sfn release current',
                'runner' => fn (): array => [
                    'summary' => $this->siteProvisioner->activateRelease($site, $releasePath),
                ],
            ],
        ];

        return $this->executeSteps($deployment, $steps, fn () => $deployment->site->update([
            'last_deployed_at' => now(),
            'current_release_path' => $releasePath,
        ]));
    }

    protected function recoveryHint(Throwable $throwable, Deployment $deployment, ?string $step = null): string
    {
        $site = $deployment->site;
        $message = strtolower($throwable->getMessage());

        return match (true) {
            str_contains($message, 'token') && str_contains($message, 'cpanel') => 'Re-run the cPanel connection wizard to refresh the API token and SSH port, then retry the deploy.',
            str_contains($message, 'repository') => 'Verify the repository URL, branch, and authentication details before retrying.',
            str_contains($message, 'local source path') => 'Confirm the local source path exists on the dashboard server and is readable.',
            str_contains($message, 'release') && str_contains($message, 'directory') => 'Re-run the bootstrap action so the release directories are recreated cleanly.',
            str_contains($message, 'timeout') || str_contains($message, 'timed out') => 'Check cPanel reachability, SSH port, and host firewall rules before retrying.',
            str_contains($message, 'permission denied') => 'Verify SSH credentials, file permissions, and any required sudo access before rerunning.',
            default => filled($step)
                ? sprintf('Review the %s output for %s and retry after fixing the blocking issue.', $step, $site->name)
                : sprintf('Review the deploy output for %s and retry once the blocking issue is fixed.', $site->name),
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
