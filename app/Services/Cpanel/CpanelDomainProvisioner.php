<?php

namespace App\Services\Cpanel;

use App\Models\Site;
use App\Services\Cpanel\CpanelSiteProvisioner;
use Illuminate\Support\Str;
use RuntimeException;

class CpanelDomainProvisioner
{
    public function __construct(
        protected CpanelApiClient $client,
        protected CpanelSiteProvisioner $siteProvisioner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $primaryDomain = trim((string) $site->primary_domain);
        $addonSubdomain = $this->addonSubdomainLabel($primaryDomain);
        $documentRoot = $this->documentRoot($site);

        return [
            'primary_domain' => $primaryDomain,
            'addon_subdomain' => $addonSubdomain,
            'document_root' => $documentRoot,
            'subdomains' => $site->subdomains ?? [],
            'alias_domains' => $site->alias_domains ?? [],
            'ssl_state' => $site->ssl_state,
            'ssl_summary' => $site->ssl_summary,
            'force_https_summary' => $site->force_https_summary,
            'steps' => [
                sprintf('Create the addon domain for %s.', $primaryDomain),
                sprintf('Map %d subdomain%s to the same document root.', count($site->subdomains ?? []), count($site->subdomains ?? []) === 1 ? '' : 's'),
                sprintf('Park %d alias domain%s on top of the addon domain.', count($site->alias_domains ?? []), count($site->alias_domains ?? []) === 1 ? '' : 's'),
                sprintf('Use %s as the addon document root.', $documentRoot),
            ],
            'notes' => [
                'cPanel will create the DNS zone for the addon domain.',
                'Subdomains are mapped under the primary domain when they match that hostname suffix.',
                'Alias domains are parked on top of the addon domain so they resolve to the same site.',
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
            throw new RuntimeException('Domain provisioning is only available for cPanel servers.');
        }

        if (! $server->can_manage_domains) {
            throw new RuntimeException('Domain management is disabled on this server. Enable the domain capability first.');
        }

        if (blank($site->primary_domain)) {
            throw new RuntimeException('The site does not have a primary domain configured.');
        }

        $primaryDomain = trim((string) $site->primary_domain);
        $addonSubdomain = $this->addonSubdomainLabel($primaryDomain);
        $documentRoot = $this->documentRoot($site);
        $summary = [];

        $this->client->ping($server);
        $summary[] = 'Validated the cPanel API connection.';

        $this->siteProvisioner->ensureWorkspace($site);
        $summary[] = sprintf('Ensured the workspace exists at %s.', rtrim((string) $site->deploy_path, '/'));

        $this->client->addAddonDomain($server, $primaryDomain, $addonSubdomain, $this->relativeDocumentRoot($server, $documentRoot));
        $summary[] = sprintf('Created the addon domain %s.', $primaryDomain);

        foreach ($site->subdomains ?? [] as $subdomain) {
            $subdomain = trim((string) (is_array($subdomain) ? ($subdomain['value'] ?? '') : $subdomain));

            if ($subdomain === '') {
                continue;
            }

            if (! str_ends_with($subdomain, '.'.$primaryDomain)) {
                $summary[] = sprintf('Skipped subdomain %s because it is not under %s.', $subdomain, $primaryDomain);

                continue;
            }

            $label = Str::before($subdomain, '.'.$primaryDomain);

            if ($label === '') {
                $summary[] = sprintf('Skipped subdomain %s because it does not have a valid label.', $subdomain);

                continue;
            }

            $this->client->addSubdomain($server, $label, $primaryDomain, $this->relativeDocumentRoot($server, $documentRoot));
            $summary[] = sprintf('Created subdomain %s.', $subdomain);
        }

        foreach ($site->alias_domains ?? [] as $aliasDomain) {
            $aliasDomain = trim((string) (is_array($aliasDomain) ? ($aliasDomain['value'] ?? '') : $aliasDomain));

            if ($aliasDomain === '') {
                continue;
            }

            $this->client->parkDomain($server, $aliasDomain, $addonSubdomain);
            $summary[] = sprintf('Parked alias domain %s.', $aliasDomain);
        }

        return $summary;
    }

    protected function addonSubdomainLabel(string $domain): string
    {
        return Str::slug(str_replace('.', ' ', strtolower(trim($domain))), '_') ?: 'site_domain';
    }

    protected function documentRoot(Site $site): string
    {
        $basePath = filled($site->current_release_path)
            ? rtrim((string) $site->current_release_path, '/')
            : rtrim((string) $site->deploy_path, '/').'/current';

        $webRoot = trim((string) ($site->web_root ?: 'public'), '/');

        return rtrim($basePath, '/').'/'.$webRoot;
    }

    protected function relativeDocumentRoot($server, string $documentRoot): string
    {
        return $this->client->toHomeRelativePath($server, $documentRoot);
    }
}
