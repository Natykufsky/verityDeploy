<?php

namespace App\Services\Servers;

use App\Models\Domain;
use App\Models\Server;
use App\Services\Cpanel\CpanelApiClient;
use Illuminate\Support\Facades\Log;

class ServerDomainSynchronizer
{
    public function __construct(
        protected CpanelApiClient $cpanel
    ) {}

    public function sync(Server $server): array
    {
        if ($server->connection_type !== 'cpanel') {
            return [
                'success' => false,
                'message' => 'Domain syncing is only supported for cPanel servers.',
            ];
        }

        try {
            $domains = $this->cpanel->listDomains($server);
            $syncedCount = 0;

            foreach ($domains as $domainData) {
                $name = data_get($domainData, 'domain');

                if (blank($name)) {
                    continue;
                }

                $cpanelType = data_get($domainData, 'type');

                $type = match ($cpanelType) {
                    'main' => 'primary',
                    'addon' => 'addon',
                    'sub' => 'subdomain',
                    'parked' => 'alias',
                    default => 'addon',
                };

                $this->updateOrCreateDomain($server, (string) $name, $type, [
                    'php_version' => data_get($domainData, 'php_version')
                        ?? data_get($domainData, 'php-version')
                        ?? data_get($domainData, 'phpversion'),
                    'web_root' => data_get($domainData, 'documentroot')
                        ?? data_get($domainData, 'webroot')
                        ?? data_get($domainData, 'rootdomain'),
                    'https_redirect' => (bool) (data_get($domainData, 'https_redirect') ?? data_get($domainData, 'is_https_redirect')),
                    'external_id' => data_get($domainData, 'user'),
                ]);

                $syncedCount++;
            }

            return [
                'success' => true,
                'count' => $syncedCount,
                'message' => "Successfully synced {$syncedCount} domains with cPanel metadata.",
            ];

        } catch (\Throwable $e) {
            Log::error('Domain Sync Failed: '.$e->getMessage(), [
                'server_id' => $server->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function updateOrCreateDomain(Server $server, string $name, string $type, array $settings = []): Domain
    {
        return Domain::updateOrCreate(
            [
                'server_id' => $server->id,
                'name' => $name,
            ],
            [
                'team_id' => $server->team_id,
                'type' => $type,
                'is_active' => true,
                'settings' => array_merge(
                    ['https_redirect' => data_get($settings, 'https_redirect')],
                    $settings
                ),
            ]
        );
    }
}
