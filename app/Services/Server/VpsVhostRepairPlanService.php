<?php

namespace App\Services\Server;

use App\Models\Server;
use App\Models\Site;
use App\Support\SiteVhostPreview;

class VpsVhostRepairPlanService
{
    public function __construct(
        protected ServerConnector $connector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $server = $site->server;
        $snapshot = (array) ($site->live_configuration_snapshot ?? []);
        $expected = $site->vhost_preview;
        $engine = strtolower((string) data_get($expected, 'engine', SiteVhostPreview::build($site)['engine'] ?? 'nginx'));

        return [
            'supported' => (bool) ($server?->can_manage_vhosts) && (($server?->connection_type ?? null) !== 'cpanel'),
            'message' => 'This shows the commands and file locations that would be used to align the live vhost config with the site intent.',
            'engine' => $engine,
            'engine_label' => ucfirst($engine),
            'vhost_path' => $this->vhostPath($site, $engine),
            'enabled_path' => $this->enabledPath($site, $engine),
            'reload_command' => $this->reloadCommand($engine),
            'snippet' => $expected['snippet'] ?? '',
            'highlights' => (array) data_get($snapshot, 'live.highlights', []),
            'expected' => $expected,
            'steps' => $this->buildSteps($site, $engine),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function commands(Site $site): array
    {
        return array_map(
            fn (array $step): string => $step['command'],
            $this->commandSteps($site),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(Site $site): array
    {
        $server = $site->server;

        if (! $server) {
            throw new \RuntimeException('The site does not have a server configured.');
        }

        if (! $this->isSupported($server)) {
            throw new \RuntimeException('VPS vhost config can only be applied when vhost management is enabled on a non-cPanel server.');
        }

        $engine = strtolower((string) data_get($site->vhost_preview, 'engine', 'nginx'));
        $strategy = $this->connector->strategy($server, 300);
        $timestamp = now();
        $steps = [];

        try {
            foreach ($this->commandSteps($site) as $index => $step) {
                $startedAt = now();
                $output = trim((string) $strategy->streamRun($step['command']));
                $finishedAt = now();

                $steps[] = [
                    'sequence' => $index + 1,
                    'label' => $step['label'],
                    'command' => $step['command'],
                    'status' => 'successful',
                    'output' => $output,
                    'started_at' => $startedAt->toIso8601String(),
                    'finished_at' => $finishedAt->toIso8601String(),
                    'exit_code' => 0,
                ];
            }

            $site->forceFill([
                'vhost_apply_last_run_at' => $timestamp,
                'vhost_apply_last_output' => trim(implode(PHP_EOL.PHP_EOL, array_filter(array_map(fn (array $step): string => sprintf('[%s] %s', $step['label'], $step['output']), $steps)))),
                'vhost_apply_last_error' => null,
                'vhost_apply_last_steps' => $steps,
            ])->save();

            app(VpsVhostInventorySyncService::class)->sync($site->fresh(['server']));
        } catch (\Throwable $throwable) {
            $steps[] = [
                'sequence' => count($steps) + 1,
                'label' => 'Apply error',
                'command' => '',
                'status' => 'failed',
                'output' => $throwable->getMessage(),
                'started_at' => $timestamp->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'exit_code' => 1,
            ];

            $site->forceFill([
                'vhost_apply_last_run_at' => $timestamp,
                'vhost_apply_last_output' => trim(implode(PHP_EOL.PHP_EOL, array_filter(array_map(fn (array $step): string => sprintf('[%s] %s', $step['label'], $step['output']), $steps)))),
                'vhost_apply_last_error' => $throwable->getMessage(),
                'vhost_apply_last_steps' => $steps,
            ])->save();

            throw $throwable;
        }

        return [
            'engine' => $engine,
            'command' => implode(PHP_EOL, array_map(fn (array $step): string => $step['command'], $steps)),
            'output' => (string) ($site->vhost_apply_last_output ?? ''),
            'steps' => $steps,
            'summary' => sprintf('Applied the %s vhost config and refreshed the live inventory snapshot.', $engine),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function buildSteps(Site $site, string $engine): array
    {
        return [
            sprintf('write the %s vhost file to %s', $engine, $this->vhostPath($site, $engine)),
            sprintf('enable the config from %s', $this->enabledPath($site, $engine)),
            sprintf('reload %s after saving the config', $engine),
        ];
    }

    /**
     * @return array<int, string>
     */
    /**
     * @return array<int, array{label: string, command: string}>
     */
    protected function commandSteps(Site $site): array
    {
        $engine = strtolower((string) data_get($site->vhost_preview, 'engine', 'nginx'));
        $vhostPath = $this->vhostPath($site, $engine);
        $enabledPath = $this->enabledPath($site, $engine);
        $snippet = $site->vhost_preview['snippet'] ?? '';

        return [
            [
                'label' => 'Prepare directories',
                'command' => sprintf('install -d %s %s', escapeshellarg(dirname($vhostPath)), escapeshellarg(dirname($enabledPath))),
            ],
            [
                'label' => 'Write vhost file',
                'command' => implode(PHP_EOL, [
                    sprintf('cat > %s <<\'EOF\'', escapeshellarg($vhostPath)),
                    $snippet,
                    'EOF',
                ]),
            ],
            [
                'label' => $engine === 'apache' ? 'Enable site' : 'Link enabled config',
                'command' => $engine === 'apache'
                    ? sprintf('a2ensite %s', basename($vhostPath))
                    : sprintf('ln -sfn %s %s', escapeshellarg($vhostPath), escapeshellarg($enabledPath)),
            ],
            [
                'label' => 'Reload web server',
                'command' => $this->reloadCommand($engine),
            ],
        ];
    }

    protected function vhostPath(Site $site, string $engine): string
    {
        $slug = $this->slugForSite($site);

        return $engine === 'apache'
            ? sprintf('/etc/apache2/sites-available/%s.conf', $slug)
            : sprintf('/etc/nginx/sites-available/%s.conf', $slug);
    }

    protected function enabledPath(Site $site, string $engine): string
    {
        $slug = $this->slugForSite($site);

        return $engine === 'apache'
            ? sprintf('/etc/apache2/sites-enabled/%s.conf', $slug)
            : sprintf('/etc/nginx/sites-enabled/%s.conf', $slug);
    }

    protected function reloadCommand(string $engine): string
    {
        return $engine === 'apache'
            ? 'systemctl reload apache2 || systemctl reload httpd'
            : 'systemctl reload nginx';
    }

    protected function slugForSite(Site $site): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($site->primary_domain ?: $site->name ?: 'site'))) ?: 'site';
    }

    protected function isSupported(Server $server): bool
    {
        return (bool) $server->can_manage_vhosts && ($server->connection_type !== 'cpanel');
    }
}
