<?php

namespace App\Services\Domains;

use App\Models\Domain;
use App\Services\Cpanel\CpanelApiClient;
use Illuminate\Support\Facades\Log;

class DomainDirectorySynchronizer
{
    public function __construct(
        protected CpanelApiClient $cpanel
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(Domain $domain): array
    {
        $domain->loadMissing('server', 'site.currentDomain');

        $server = $domain->server;
        $site = $domain->site;
        $webRoot = trim((string) ($domain->web_root ?: ''));

        if (! $server || ! $site || $webRoot === '') {
            return [
                'success' => false,
                'synced' => false,
                'message' => 'The domain is missing a linked server, site, or directory.',
            ];
        }

        if ($server->connection_type !== 'cpanel') {
            return [
                'success' => true,
                'synced' => false,
                'message' => 'The directory was saved locally. Live cPanel sync is only available on cPanel servers.',
            ];
        }

        $rootDomain = $site->currentDomain?->name ?? $site->primary_domain;

        if ($domain->type !== 'subdomain' || blank($rootDomain)) {
            return [
                'success' => true,
                'synced' => false,
                'message' => 'This domain type is saved locally. Live cPanel document-root updates are only supported for subdomains in this release.',
            ];
        }

        try {
            $directory = $this->cpanel->toHomeRelativePath($server, $webRoot);
            $this->cpanel->changeSubdomainDocroot($server, $domain->name, (string) $rootDomain, $directory);

            return [
                'success' => true,
                'synced' => true,
                'message' => sprintf('Updated the live cPanel document root for %s.', $domain->name),
            ];
        } catch (\Throwable $throwable) {
            Log::error('Domain directory sync failed: '.$throwable->getMessage(), [
                'domain_id' => $domain->id,
                'server_id' => $server->id,
            ]);

            return [
                'success' => false,
                'synced' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }
}
