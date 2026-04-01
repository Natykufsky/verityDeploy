<?php

namespace App\Services\Cpanel;

use App\Models\Site;
use RuntimeException;

class CpanelSslProvisioner
{
    public function __construct(protected CpanelApiClient $client)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        return [
            'supported' => ($site->server?->connection_type ?? null) === 'cpanel' && (bool) ($site->server?->can_manage_ssl ?? false),
            'message' => 'This will generate a cPanel SSL certificate for the site primary domain and mark the site as ssl ready.',
            'primary_domain' => $site->primary_domain,
            'ssl_state' => $site->ssl_state,
            'force_https' => (bool) $site->force_https,
            'steps' => [
                'Generate a self-signed certificate for the site host.',
                'Record the new certificate state on the site.',
                'Enable HTTPS redirects when force https is enabled.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function provision(Site $site): array
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('SSL automation currently uses the cPanel SSL API.');
        }

        if (! $server->can_manage_ssl) {
            throw new RuntimeException('SSL management is disabled on this server. Enable the SSL capability first.');
        }

        if (blank($site->primary_domain)) {
            throw new RuntimeException('The site does not have a primary domain configured.');
        }

        $domain = trim((string) $site->primary_domain);

        $this->client->requestApi2($server, 'SSL', 'gencrt', [
            'city' => 'Lagos',
            'company' => 'verityDeploy',
            'companydivision' => 'Deployment',
            'country' => 'NG',
            'email' => 'admin@'.$domain,
            'host' => $domain,
            'state' => 'LA',
        ]);

        $site->update([
            'ssl_state' => 'valid',
            'ssl_last_synced_at' => now(),
            'ssl_last_error' => null,
            'force_https' => (bool) $site->force_https,
        ]);

        return [
            sprintf('Generated a cPanel SSL certificate for %s.', $domain),
            'Marked the site ssl state as valid.',
            $site->force_https ? 'HTTPS redirects remain enabled.' : 'HTTPS redirects remain optional until you toggle them on.',
        ];
    }
}
