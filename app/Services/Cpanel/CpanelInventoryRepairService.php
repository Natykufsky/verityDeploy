<?php

namespace App\Services\Cpanel;

use App\Models\Site;
use RuntimeException;

class CpanelInventoryRepairService
{
    public function __construct(
        protected CpanelDomainProvisioner $domainProvisioner,
        protected CpanelSslProvisioner $sslProvisioner,
        protected CpanelInventorySyncService $inventorySyncService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function repair(Site $site): array
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('Inventory repair is currently available for cPanel servers only.');
        }

        if (! filled($server->effectiveCpanelApiToken())) {
            throw new RuntimeException('The cPanel API token is required before inventory repair can run. Attach a cPanel Credential Profile.');
        }

        $summary = [];

        if ((bool) $server->can_manage_domains && filled($site->primary_domain)) {
            $summary = array_merge($summary, $this->domainProvisioner->provision($site->fresh(['server'])));
        } else {
            $summary[] = 'Skipped domain provisioning because the server capability or primary domain is missing.';
        }

        if ((bool) $server->can_manage_ssl && filled($site->primary_domain)) {
            $summary = array_merge($summary, $this->sslProvisioner->provision($site->fresh(['server'])));
        } else {
            $summary[] = 'Skipped SSL provisioning because the server capability or primary domain is missing.';
        }

        $summary = array_merge($summary, $this->inventorySyncService->sync($site->fresh(['server'])));
        $summary[] = 'Refreshed the live inventory snapshot after repair.';

        return $summary;
    }
}
