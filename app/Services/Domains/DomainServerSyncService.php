<?php

namespace App\Services\Domains;

use App\Models\Domain;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelDomainProvisioner;
use App\Services\Cpanel\CpanelInventorySyncService;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DomainServerSyncService
{
    public function __construct(
        protected CpanelApiClient $client,
        protected CpanelDomainProvisioner $domainProvisioner,
        protected CpanelInventorySyncService $inventorySyncService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function syncCreated(Domain $domain): array
    {
        return $this->syncDomain($domain, 'created');
    }

    /**
     * @return array<string, mixed>
     */
    public function syncUpdated(Domain $domain): array
    {
        return $this->syncDomain($domain, 'updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function syncDeleted(Domain $domain): array
    {
        return $this->syncDomain($domain, 'deleted');
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncDomain(Domain $domain, string $action): array
    {
        $domain->loadMissing('server', 'site.currentDomain');
        $server = $domain->server;
        $site = $domain->site;

        if (! $server || $server->connection_type !== 'cpanel' || ! filled($server->effectiveCpanelApiToken())) {
            return [
                'success' => true,
                'synced' => false,
                'message' => 'Saved locally. Live cPanel sync is only available on cPanel servers with an API token.',
            ];
        }

        if (! $server->can_manage_domains) {
            return [
                'success' => true,
                'synced' => false,
                'message' => 'Saved locally. Domain management is disabled on this server.',
            ];
        }

        try {
            $summary = match ($action) {
                'created' => $this->syncCreate($domain),
                'updated' => $this->syncUpdate($domain),
                'deleted' => $this->syncDelete($domain),
                default => throw new RuntimeException('Unknown domain sync action.'),
            };

            if ($site && $site->server_id === $server->id) {
                try {
                    $this->inventorySyncService->sync($site->fresh(['server']));
                } catch (Throwable) {
                    // Keep the domain action successful even if inventory refresh fails.
                }
            }

            return [
                'success' => true,
                'synced' => true,
                'message' => implode(' ', array_filter($summary)),
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'synced' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    protected function syncCreate(Domain $domain): array
    {
        if ($domain->type === 'primary') {
            if ($domain->site) {
                return $this->domainProvisioner->provision($domain->site->fresh(['server']));
            }

            return ['Primary domains are provisioned from the site wizard.'];
        }

        return match ($domain->type) {
            'addon' => $this->createAddonDomain($domain),
            'subdomain' => $this->createSubdomain($domain),
            'alias' => $this->createAlias($domain),
            default => ['Unsupported domain type; saved locally only.'],
        };
    }

    /**
     * @return array<int, string>
     */
    protected function syncUpdate(Domain $domain): array
    {
        if ($domain->type === 'primary') {
            if ($domain->site) {
                return $this->domainProvisioner->provision($domain->site->fresh(['server']));
            }

            return ['Primary domains are provisioned from the site wizard.'];
        }

        $changes = array_keys($domain->getChanges());
        $routingFields = ['name', 'type', 'web_root', 'site_id'];
        $hasRoutingChanges = (bool) array_intersect($changes, $routingFields);

        if (! $hasRoutingChanges) {
            return ['Updated the local domain record and refreshed the live inventory snapshot.'];
        }

        $originalType = (string) ($domain->getOriginal('type') ?: $domain->type);
        $originalName = trim((string) ($domain->getOriginal('name') ?: $domain->name));
        $nameChanged = $originalName !== trim((string) $domain->name);
        $typeChanged = $originalType !== $domain->type;

        if ($nameChanged || $typeChanged) {
            $this->syncDeleteSnapshot($domain, $originalType, $originalName);

            return $this->syncCreate($domain);
        }

        return match ($domain->type) {
            'addon' => $this->recreateAddonDocroot($domain),
            'subdomain' => $this->updateSubdomainDocroot($domain),
            'alias' => $this->recreateAlias($domain),
            default => ['Unsupported domain type; saved locally only.'],
        };
    }

    /**
     * @return array<int, string>
     */
    protected function syncDelete(Domain $domain): array
    {
        if ($domain->type === 'primary') {
            return ['Primary domains are managed from the site provisioning flow.'];
        }

        return $this->syncDeleteSnapshot($domain, (string) $domain->type, trim((string) $domain->name));
    }

    /**
     * @return array<int, string>
     */
    protected function createAddonDomain(Domain $domain): array
    {
        $site = $domain->site;
        $rootDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($rootDomain)) {
            throw new RuntimeException('A linked primary domain is required before provisioning an addon domain.');
        }

        $directory = $this->relativeDocumentRoot($domain);
        $subdomain = $this->addonSubdomainLabel($domain->name);

        $this->client->addAddonDomain($domain->server, $domain->name, $subdomain, $directory);

        return [sprintf('Created addon domain %s on cPanel.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function createSubdomain(Domain $domain): array
    {
        $site = $domain->site;
        $rootDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($rootDomain)) {
            throw new RuntimeException('A linked primary domain is required before provisioning a subdomain.');
        }

        $label = $this->subdomainLabel($domain->name, (string) $rootDomain);
        $directory = $this->relativeDocumentRoot($domain);

        $this->client->addSubdomain($domain->server, $label, (string) $rootDomain, $directory);

        return [sprintf('Created subdomain %s on cPanel.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function createAlias(Domain $domain): array
    {
        $site = $domain->site;
        $topDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($topDomain)) {
            throw new RuntimeException('A linked primary domain is required before provisioning an alias domain.');
        }

        $this->client->parkDomain($domain->server, $domain->name, (string) $topDomain);

        return [sprintf('Parked alias domain %s on cPanel.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function updateSubdomainDocroot(Domain $domain): array
    {
        $site = $domain->site;
        $rootDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($rootDomain)) {
            throw new RuntimeException('A linked primary domain is required before updating a subdomain document root.');
        }

        $label = $this->subdomainLabel($domain->name, (string) $rootDomain);
        $directory = $this->relativeDocumentRoot($domain);

        $this->client->changeSubdomainDocroot($domain->server, $label, (string) $rootDomain, $directory);

        return [sprintf('Updated the live document root for %s.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function recreateAddonDocroot(Domain $domain): array
    {
        $site = $domain->site;
        $rootDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($rootDomain)) {
            throw new RuntimeException('A linked primary domain is required before updating an addon domain.');
        }

        $subdomain = $this->addonSubdomainLabel($domain->name);
        $this->client->delAddonDomain($domain->server, $domain->name, $subdomain);
        $this->client->addAddonDomain($domain->server, $domain->name, $subdomain, $this->relativeDocumentRoot($domain));

        return [sprintf('Recreated addon domain %s with the updated directory.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function recreateAlias(Domain $domain): array
    {
        $site = $domain->site;
        $topDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($topDomain)) {
            throw new RuntimeException('A linked primary domain is required before updating an alias domain.');
        }

        $this->client->unparkDomain($domain->server, $domain->name, (string) $topDomain);
        $this->client->parkDomain($domain->server, $domain->name, (string) $topDomain);

        return [sprintf('Recreated alias domain %s on cPanel.', $domain->name)];
    }

    /**
     * @return array<int, string>
     */
    protected function syncDeleteSnapshot(Domain $domain, string $type, string $name): array
    {
        return match ($type) {
            'addon' => $this->deleteAddon($domain, $name),
            'subdomain' => $this->deleteSubdomain($domain, $name),
            'alias' => $this->deleteAlias($domain, $name),
            default => ['Unsupported domain type; removed locally only.'],
        };
    }

    /**
     * @return array<int, string>
     */
    protected function deleteAddon(Domain $domain, string $name): array
    {
        $subdomain = $this->addonSubdomainLabel($name);
        $this->client->delAddonDomain($domain->server, $name, $subdomain);

        return [sprintf('Deleted addon domain %s from cPanel.', $name)];
    }

    /**
     * @return array<int, string>
     */
    protected function deleteSubdomain(Domain $domain, string $name): array
    {
        $this->client->deleteSubdomain($domain->server, $name);

        return [sprintf('Deleted subdomain %s from cPanel.', $name)];
    }

    /**
     * @return array<int, string>
     */
    protected function deleteAlias(Domain $domain, string $name): array
    {
        $site = $domain->site;
        $topDomain = $site?->currentDomain?->name ?? $site?->primary_domain;

        if (blank($topDomain)) {
            throw new RuntimeException('A linked primary domain is required before deleting an alias domain.');
        }

        $this->client->unparkDomain($domain->server, $name, (string) $topDomain);

        return [sprintf('Removed alias domain %s from cPanel.', $name)];
    }

    protected function addonSubdomainLabel(string $domain): string
    {
        return Str::slug(str_replace('.', ' ', strtolower(trim($domain))), '_') ?: 'site_domain';
    }

    protected function subdomainLabel(string $domain, string $rootDomain): string
    {
        $label = Str::before($domain, '.'.$rootDomain);

        if ($label === '') {
            $label = Str::slug($domain, '_');
        }

        return $label ?: 'subdomain';
    }

    protected function relativeDocumentRoot(Domain $domain): string
    {
        $webRoot = trim((string) ($domain->web_root ?: 'public'));
        $directory = filled($webRoot) ? $webRoot : 'public';

        return $this->client->toHomeRelativePath($domain->server, $directory);
    }
}
