<?php

namespace App\Services\Server;

use App\Models\Server;
use App\Models\Site;
use App\Services\Server\Connections\ConnectionStrategy;
use App\Support\SiteVhostPreview;
use RuntimeException;
use Throwable;

class VpsVhostInventorySyncService
{
    public function __construct(protected ServerConnector $connector)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $server = $site->server;
        $supported = $this->isSupported($server);
        $expected = SiteVhostPreview::build($site);

        return [
            'supported' => $supported,
            'message' => $supported
                ? 'This will inspect the live web server config and store a normalized vhost snapshot on the site.'
                : 'Vhost inventory sync is only available for servers with vhost management enabled.',
            'source' => 'VPS',
            'engine' => $expected['engine'] ?? 'nginx',
            'engine_label' => $expected['engine_label'] ?? 'Nginx',
            'expected' => $expected,
            'synced_at' => $site->live_configuration_synced_at?->format('M d, Y H:i') ?? 'never synced',
            'last_error' => $site->live_configuration_last_error ?: 'No sync errors recorded.',
            'steps' => [
                'Read the live web server config from the remote host.',
                'Extract hostnames, roots, and SSL directives.',
                'Compare the live config with the site intent.',
                'Store a normalized snapshot on the site record.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sync(Site $site): array
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if (! $this->isSupported($server)) {
            throw new RuntimeException('Vhost inventory sync is only available when vhost management is enabled on the server.');
        }

        $strategy = $this->connector->strategy($server, 120);
        $engine = $this->determineEngine($server);
        $expected = SiteVhostPreview::build($site);
        $command = $this->inventoryCommand($engine);
        $rawOutput = trim($strategy->streamRun($command));
        $highlights = $this->extractHighlights($rawOutput);

        $snapshot = [
            'source' => 'vps',
            'synced_at' => now()->toIso8601String(),
            'account' => [
                'server_name' => $server->name,
                'provider' => $server->provider_label,
                'engine' => $engine,
            ],
            'expected' => $expected,
            'live' => [
                'engine' => $engine,
                'raw_output' => $rawOutput,
                'highlights' => $highlights,
                'hostnames' => $expected['hostnames'] ?? [],
                'document_root' => $expected['document_root'] ?? null,
                'ssl_state' => $expected['ssl_state'] ?? 'unconfigured',
                'force_https' => (bool) ($expected['force_https'] ?? false),
            ],
            'notes' => array_values(array_filter([
                'This snapshot is read-only and reflects the web server config the host exposes over SSH.',
                'DNS is not managed from the VPS inventory view, so the site DNS preview remains the source of DNS intent.',
                'The comparison view highlights drift between the live config and the site configuration preview.',
            ])),
        ];

        $site->forceFill([
            'live_configuration_snapshot' => $snapshot,
            'live_configuration_synced_at' => now(),
            'live_configuration_last_error' => null,
        ])->save();

        return [
            sprintf('Read the live %s configuration from the remote host.', $engine),
            sprintf('Captured %d highlighted config line%s.', count($highlights), count($highlights) === 1 ? '' : 's'),
            'Stored the normalized vhost snapshot on the site record.',
        ];
    }

    protected function isSupported(?Server $server): bool
    {
        return (bool) $server
            && $server->connection_type !== 'cpanel'
            && (bool) ($server->can_manage_vhosts ?? false);
    }

    protected function determineEngine(Server $server): string
    {
        return in_array($server->provider_type, ['aws', 'digitalocean', 'hetzner', 'vultr', 'linode', 'local', 'manual'], true)
            ? 'nginx'
            : 'apache';
    }

    protected function inventoryCommand(string $engine): string
    {
        if ($engine === 'apache') {
            return <<<'BASH'
if command -v apache2ctl >/dev/null 2>&1; then
    apache2ctl -S 2>&1
elif command -v apachectl >/dev/null 2>&1; then
    apachectl -S 2>&1
elif command -v httpd >/dev/null 2>&1; then
    httpd -S 2>&1
else
    printf '%s\n' 'apache command not found'
fi
BASH;
        }

        return <<<'BASH'
if command -v nginx >/dev/null 2>&1; then
    nginx -T 2>&1
else
    printf '%s\n' 'nginx command not found'
fi
BASH;
    }

    /**
     * @return array<int, string>
     */
    protected function extractHighlights(string $output): array
    {
        $patterns = [
            '/^\s*server_name\s+.+$/mi',
            '/^\s*root\s+.+$/mi',
            '/^\s*DocumentRoot\s+.+$/mi',
            '/^\s*ServerName\s+.+$/mi',
            '/^\s*ServerAlias\s+.+$/mi',
            '/^\s*ssl_certificate(?:_key)?\s+.+$/mi',
            '/^\s*SSLCertificate(?:File|KeyFile)\s+.+$/mi',
            '/^\s*listen\s+443.*$/mi',
        ];

        $lines = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                foreach ($matches[0] as $line) {
                    $line = trim((string) $line);
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }
            }
        }

        if ($lines === []) {
            $lines = collect(preg_split('/\R/', $output) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter(fn (string $line): bool => $line !== '')
                ->take(12)
                ->values()
                ->all();
        }

        return array_values(array_unique($lines));
    }
}
