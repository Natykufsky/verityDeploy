<?php

namespace App\Services\Cpanel;

use App\Actions\BootstrapDeployPath;
use App\Models\CpanelWizardRun;
use App\Models\Server;
use App\Models\Site;
use App\Services\Server\ServerProvisioner;
use RuntimeException;
use Throwable;

class CpanelWizardRunner
{
    public function __construct(
        protected CpanelApiClient $client,
        protected ServerProvisioner $serverProvisioner,
        protected BootstrapDeployPath $bootstrapDeployPath,
    ) {}

    /**
     * @return array<int, array{step: string, status: string, message: string, timestamp: string}>
     */
    public function runServerChecks(Server $server): array
    {
        $server = $server->fresh();

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('The wizard can only run on cPanel servers.');
        }

        $run = $this->startRun(
            wizardType: 'server_checks',
            server: $server,
            site: null,
        );

        $steps = [];

        try {
            $this->runServerCheckSteps($server, $run, $steps);

            $this->finishRun($run, $steps, 'successful', null);

            return $steps;
        } catch (Throwable $throwable) {
            $hint = $this->recoveryHint($throwable, 'server_checks', $server);
            $this->appendStep($run, $steps, 'Recovery guidance', 'failed', $hint);
            $this->finishRun($run, $steps, 'failed', $throwable->getMessage(), $hint);

            throw $throwable;
        }
    }

    /**
     * @return array<int, array{step: string, status: string, message: string, timestamp: string}>
     */
    public function runSiteBootstrap(Site $site): array
    {
        $site = $site->fresh(['server']);

        if ($site->server?->connection_type !== 'cpanel') {
            throw new RuntimeException('The wizard can only bootstrap cPanel sites.');
        }

        $run = $this->startRun(
            wizardType: 'site_bootstrap',
            server: $site->server,
            site: $site,
        );

        $steps = [];

        try {
            $this->runServerCheckSteps($site->server->fresh(), $run, $steps);

            $this->appendStep($run, $steps, 'Bootstrap deploy path', 'running', 'Bootstrapping the release directories and shared runtime...');
            $bootstrapSummary = $this->bootstrapDeployPath->bootstrapAfterPreflight($site->fresh(['server']));
            $this->appendStep(
                $run,
                $steps,
                'Bootstrap deploy path',
                'successful',
                filled($bootstrapSummary) ? implode(PHP_EOL, $bootstrapSummary) : 'The deployment path was bootstrapped successfully.',
            );

            $this->finishRun($run, $steps, 'successful', null);

            return $steps;
        } catch (Throwable $throwable) {
            $hint = $this->recoveryHint($throwable, 'site_bootstrap', $site->server, $site);
            $this->appendStep($run, $steps, 'Recovery guidance', 'failed', $hint);
            $this->finishRun($run, $steps, 'failed', $throwable->getMessage(), $hint);

            throw $throwable;
        }
    }

    /**
     * @param  array<int, array{step: string, status: string, message: string, timestamp: string}>  $steps
     */
    protected function runServerCheckSteps(Server $server, CpanelWizardRun $run, array &$steps): void
    {
        $this->appendStep($run, $steps, 'discover ssh port', 'running', 'querying cpanel ssh/get_port...');
        $port = $this->client->discoverSshPort($server->fresh());

        $server->update([
            'ssh_port' => $port,
        ]);

        $this->appendStep($run, $steps, 'discover ssh port', 'successful', "the cpanel account ssh port is {$port}.");

        $this->appendStep($run, $steps, 'test cpanel api', 'running', 'pinging the cpanel api token and port...');
        $this->client->ping($server->fresh());
        $server->update([
            'status' => 'online',
            'last_connected_at' => now(),
        ]);
        $this->appendStep($run, $steps, 'test cpanel api', 'successful', 'the cpanel api responded successfully.');

        $this->appendStep($run, $steps, 'server provisioning', 'running', 'running the server provisioning preflight...');
        $test = $this->serverProvisioner->preflight($server->fresh());
        $this->appendStep(
            $run,
            $steps,
            'server provisioning',
            'successful',
            filled($test->output) ? (string) $test->output : 'server provisioning preflight completed.',
        );
    }

    /**
     * @param  array<int, array{step: string, status: string, message: string, timestamp: string}>  $steps
     */
    protected function startRun(string $wizardType, ?Server $server, ?Site $site): CpanelWizardRun
    {
        return CpanelWizardRun::query()->create([
            'server_id' => $server?->id,
            'site_id' => $site?->id,
            'wizard_type' => $wizardType,
            'status' => 'running',
            'steps' => [],
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{step: string, status: string, message: string, timestamp: string}>  $steps
     */
    protected function finishRun(CpanelWizardRun $run, array $steps, string $status, ?string $errorMessage, ?string $recoveryHint = null): void
    {
        $run->update([
            'status' => $status,
            'steps' => $steps,
            'summary' => $this->summarizeSteps($steps),
            'error_message' => $errorMessage,
            'recovery_hint' => $recoveryHint,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{step: string, status: string, message: string, timestamp: string}>  $steps
     */
    protected function summarizeSteps(array $steps): string
    {
        return collect($steps)
            ->map(fn (array $step): string => sprintf('%s: %s', $step['step'], $step['message']))
            ->implode(PHP_EOL);
    }

    /**
     * @param  array<int, array{step: string, status: string, message: string, timestamp: string}>  $steps
     */
    protected function appendStep(CpanelWizardRun $run, array &$steps, string $step, string $status, string $message): void
    {
        $steps[] = [
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ];

        $run->update([
            'steps' => $steps,
            'summary' => $this->summarizeSteps($steps),
        ]);
    }

    protected function recoveryHint(Throwable $throwable, string $wizardType, ?Server $server = null, ?Site $site = null): string
    {
        $message = strtolower($throwable->getMessage());

        return match (true) {
            str_contains($message, 'ssh/get_port') || str_contains($message, 'ssh port') => 'The cPanel API token appears to be valid, but the SSH port lookup failed. Confirm that the cPanel account has SSH access enabled, rediscover the account SSH port, and then rerun the connection wizard.',
            str_contains($message, 'token') && str_contains($message, 'cpanel') => 'Re-check the cPanel API token and rerun the connection wizard.',
            str_contains($message, 'unauthorized') || str_contains($message, '401') || str_contains($message, 'forbidden') || str_contains($message, '403') => 'The cPanel API token was rejected. Re-check the token, the cPanel username, and the API port before rerunning the wizard.',
            str_contains($message, 'invalid response') || str_contains($message, 'failed to decode') => 'The cPanel API responded unexpectedly. Confirm the API port and that the server returns cPanel JSON, then rerun the wizard.',
            str_contains($message, 'ssh') && str_contains($message, 'port') => 'Rediscover the SSH port, then verify firewall access and retry.',
            str_contains($message, 'timeout') || str_contains($message, 'timed out') => 'Confirm network reachability and the cPanel host port, then rerun the wizard.',
            str_contains($message, 'permission denied') => 'Verify SSH credentials, sudo access, and file permissions before trying again.',
            str_contains($message, 'bootstrap') && filled($site) => sprintf('Re-run the site bootstrap wizard for %s after confirming the deploy path is writable.', $site->name),
            default => sprintf(
                'Review the %s wizard output, fix the blocking issue, and rerun the wizard.',
                $wizardType === 'site_bootstrap' ? 'site bootstrap' : 'server connection'
            ),
        };
    }
}
