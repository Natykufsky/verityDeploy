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

    /**
     * @return array<string, mixed>
     */
    public function preview(Server $server): array
    {
        if ($server->connection_type !== 'cpanel') {
            return [
                'supported' => false,
                'message' => 'Live domain inventory is only available for cPanel servers.',
                'domains' => [],
                'counts' => [
                    'total' => 0,
                    'primary' => 0,
                    'addon' => 0,
                    'subdomain' => 0,
                    'alias' => 0,
                    'ssl_enabled' => 0,
                    'active' => 0,
                    'site_count' => 0,
                ],
            ];
        }

        try {
            $domainsPayload = $this->cpanel->request($server, 'DomainInfo', 'domains_data');
            $domains = $this->normalizeDomains($domainsPayload);

            return [
                'supported' => true,
                'message' => 'This is the live cPanel domain inventory and updates whenever the server page refreshes.',
                'synced_at' => now()->format('M d, Y H:i'),
                'domains' => $domains,
                'counts' => [
                    'total' => count($domains),
                    'primary' => collect($domains)->where('type', 'primary')->count(),
                    'addon' => collect($domains)->where('type', 'addon')->count(),
                    'subdomain' => collect($domains)->where('type', 'subdomain')->count(),
                    'alias' => collect($domains)->where('type', 'alias')->count(),
                    'ssl_enabled' => collect($domains)->where('is_ssl_enabled', true)->count(),
                    'active' => collect($domains)->where('is_active', true)->count(),
                    'site_count' => collect($domains)
                        ->pluck('site_id')
                        ->filter()
                        ->unique()
                        ->count(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Domain preview failed: '.$e->getMessage(), [
                'server_id' => $server->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'supported' => false,
                'message' => $e->getMessage(),
                'domains' => [],
                'counts' => [
                    'total' => 0,
                    'primary' => 0,
                    'addon' => 0,
                    'subdomain' => 0,
                    'alias' => 0,
                    'ssl_enabled' => 0,
                    'active' => 0,
                    'site_count' => 0,
                ],
            ];
        }
    }

    public function sync(Server $server): array
    {
        if ($server->connection_type !== 'cpanel') {
            return [
                'success' => false,
                'message' => 'Domain syncing is only supported for cPanel servers.',
            ];
        }

        try {
            $domains = $this->preview($server)['domains'] ?? [];
            $sites = $server->sites()
                ->with('primaryDomain')
                ->get(['id', 'primary_domain_id']);
            $syncedCount = 0;

            foreach ($domains as $domainData) {
                $name = trim((string) data_get($domainData, 'domain'));

                if (blank($name)) {
                    continue;
                }

                $this->updateOrCreateDomain($server, $name, $this->normalizeType(data_get($domainData, 'type')), [
                    'site_id' => $this->resolveSiteId($sites, $name),
                    'php_version' => data_get($domainData, 'php_version')
                        ?? data_get($domainData, 'php-version')
                        ?? data_get($domainData, 'phpversion'),
                    'web_root' => data_get($domainData, 'documentroot')
                        ?? data_get($domainData, 'document_root')
                        ?? data_get($domainData, 'webroot')
                        ?? data_get($domainData, 'dir')
                        ?? data_get($domainData, 'rootdomain'),
                    'is_ssl_enabled' => (bool) (data_get($domainData, 'is_ssl_enabled')
                        ?? data_get($domainData, 'installed')
                        ?? data_get($domainData, 'has_ssl')
                        ?? data_get($domainData, 'ssl_exists')),
                    'ssl_status' => data_get($domainData, 'ssl_status')
                        ?? data_get($domainData, 'status'),
                    'ssl_expires_at' => data_get($domainData, 'ssl_expires_at')
                        ?? data_get($domainData, 'not_after')
                        ?? data_get($domainData, 'expires_on')
                        ?? data_get($domainData, 'expiry'),
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

    public function syncServers(iterable $servers): array
    {
        $results = [];

        foreach ($servers as $server) {
            if ($server instanceof Server) {
                $results[] = $this->sync($server);
            }
        }

        return $results;
    }

    protected function updateOrCreateDomain(Server $server, string $name, string $type, array $settings = []): Domain
    {
        return Domain::withoutEvents(function () use ($server, $name, $type, $settings): Domain {
            return Domain::updateOrCreate(
                [
                    'server_id' => $server->id,
                    'name' => $name,
                ],
                [
                    'team_id' => $server->team_id,
                    'site_id' => data_get($settings, 'site_id'),
                    'type' => $type,
                    'is_active' => true,
                    'php_version' => data_get($settings, 'php_version'),
                    'web_root' => data_get($settings, 'web_root'),
                    'is_ssl_enabled' => data_get($settings, 'is_ssl_enabled', false),
                    'ssl_status' => data_get($settings, 'ssl_status'),
                    'ssl_expires_at' => data_get($settings, 'ssl_expires_at'),
                    'external_id' => data_get($settings, 'external_id'),
                    'settings' => array_merge(
                        [
                            'https_redirect' => data_get($settings, 'https_redirect'),
                            'source' => 'cpanel',
                        ],
                        $settings
                    ),
                ]
            );
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDomains(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $domains = collect()
            ->merge($this->normalizeDomainBucket(data_get($payload, 'domains'), null))
            ->merge($this->normalizeDomainBucket(data_get($payload, 'main_domain') ?? data_get($payload, 'domain'), 'primary'))
            ->merge($this->normalizeDomainBucket(data_get($payload, 'addon_domains'), 'addon'))
            ->merge($this->normalizeDomainBucket(data_get($payload, 'sub_domains') ?? data_get($payload, 'subdomains'), 'subdomain'))
            ->merge($this->normalizeDomainBucket(data_get($payload, 'parked_domains') ?? data_get($payload, 'alias_domains'), 'alias'))
            ->filter(fn (array $item): bool => filled($item['domain'] ?? null))
            ->unique(fn (array $item): string => strtolower(trim((string) $item['domain'])))
            ->values()
            ->all();

        if (! empty($domains)) {
            return $domains;
        }

        return $this->normalizeDomainBucket($payload, null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDomainBucket(mixed $items, ?string $defaultType): array
    {
        if (is_string($items)) {
            $items = [$items];
        }

        if (! is_array($items)) {
            return [];
        }

        if (! array_is_list($items) && $this->looksLikeDomainEntry($items)) {
            $items = [$items];
        }

        return collect($items)
            ->map(fn (mixed $item): ?array => $this->normalizeDomainEntry($item, $defaultType))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeDomainEntry(mixed $item, ?string $defaultType = null): ?array
    {
        if (is_string($item)) {
            $domain = trim($item);

            return $domain === '' ? null : [
                'domain' => $domain,
                'type' => $defaultType ?? 'addon',
                'php_version' => null,
                'documentroot' => null,
                'is_ssl_enabled' => false,
                'ssl_status' => null,
                'ssl_expires_at' => null,
                'user' => null,
            ];
        }

        if (! is_array($item)) {
            return null;
        }

        $domain = $this->firstFilled([
            data_get($item, 'domain'),
            data_get($item, 'domain_name'),
            data_get($item, 'name'),
            data_get($item, 'fullsubdomain'),
            data_get($item, 'subdomain'),
            data_get($item, 'main_domain'),
        ]);

        if (! filled($domain)) {
            return null;
        }

        $type = $this->normalizeType(data_get($item, 'type') ?? data_get($item, 'domain_type') ?? $defaultType);

        return [
            'domain' => (string) $domain,
            'type' => $type,
            'php_version' => $this->firstFilled([
                data_get($item, 'php_version'),
                data_get($item, 'php-version'),
                data_get($item, 'phpversion'),
            ]),
            'documentroot' => $this->firstFilled([
                data_get($item, 'documentroot'),
                data_get($item, 'document_root'),
                data_get($item, 'webroot'),
                data_get($item, 'dir'),
                data_get($item, 'path'),
                data_get($item, 'rootdomain'),
            ]),
            'is_ssl_enabled' => (bool) $this->firstFilled([
                data_get($item, 'is_ssl_enabled'),
                data_get($item, 'installed'),
                data_get($item, 'has_ssl'),
                data_get($item, 'ssl_exists'),
            ], false),
            'ssl_status' => $this->firstFilled([
                data_get($item, 'ssl_status'),
                data_get($item, 'status'),
            ]),
            'ssl_expires_at' => $this->firstFilled([
                data_get($item, 'ssl_expires_at'),
                data_get($item, 'not_after'),
                data_get($item, 'expires_on'),
                data_get($item, 'expiry'),
            ]),
            'user' => data_get($item, 'user'),
        ];
    }

    protected function normalizeType(mixed $type): string
    {
        return match (strtolower(trim((string) $type))) {
            'main', 'primary' => 'primary',
            'addon' => 'addon',
            'sub', 'subdomain', 'sub_domains' => 'subdomain',
            'parked', 'alias' => 'alias',
            default => 'addon',
        };
    }

    protected function looksLikeDomainEntry(array $item): bool
    {
        return filled(data_get($item, 'domain'))
            || filled(data_get($item, 'domain_name'))
            || filled(data_get($item, 'name'))
            || filled(data_get($item, 'main_domain'))
            || filled(data_get($item, 'subdomain'));
    }

    protected function resolveSiteId($sites, string $domain): ?int
    {
        $normalizedDomain = strtolower(trim($domain));

        foreach ($sites as $site) {
            if (strtolower(trim((string) ($site->primaryDomain?->name ?? ''))) === $normalizedDomain) {
                return $site->id;
            }

        }

        return null;
    }

    protected function firstFilled(array $values, mixed $default = null): mixed
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return $default;
    }
}
