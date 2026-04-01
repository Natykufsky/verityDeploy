<?php

namespace App\Services\Server;

use App\Models\Site;
use App\Support\SiteVhostPreview;

class VpsVhostRepairPlanService
{
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
        $engine = strtolower((string) data_get($site->vhost_preview, 'engine', 'nginx'));
        $vhostPath = $this->vhostPath($site, $engine);
        $enabledPath = $this->enabledPath($site, $engine);
        $snippet = $site->vhost_preview['snippet'] ?? '';

        return [
            sprintf('cat > %s <<\'EOF\'', escapeshellarg($vhostPath)),
            $snippet,
            'EOF',
            $engine === 'apache'
                ? sprintf('a2ensite %s', basename($vhostPath))
                : sprintf('ln -sfn %s %s', escapeshellarg($vhostPath), escapeshellarg($enabledPath)),
            $this->reloadCommand($engine),
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
}
