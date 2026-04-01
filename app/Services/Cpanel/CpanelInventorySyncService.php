<?php

namespace App\Services\Cpanel;

use App\Models\Server;
use App\Models\Site;
use RuntimeException;
use Throwable;

class CpanelInventorySyncService
{
    public function __construct(protected CpanelApiClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $server = $site->server;
        $supported = $this->isSupported($server);

        return [
            'supported' => $supported,
            'message' => $supported
                ? 'This will fetch the live cPanel domain, DNS, and SSL inventory and store a normalized snapshot on the site.'
                : 'Live inventory sync is only available for cPanel servers with an API token.',
            'source' => 'cPanel',
            'primary_domain' => $site->primary_domain,
            'synced_at' => $site->live_configuration_synced_at?->format('M d, Y H:i') ?? 'never synced',
            'last_error' => $site->live_configuration_last_error ?: 'No sync errors recorded.',
            'steps' => [
                'Fetch the account domain inventory from cPanel.',
                'Fetch SSL host data for the account.',
                'Fetch DNS zone records for the primary domain when available.',
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
            throw new RuntimeException('Live inventory sync is only available for cPanel servers with an API token.');
        }

        try {
            $domainsPayload = $this->client->request($server, 'DomainInfo', 'list_domains');
            $sslPayload = $this->client->request($server, 'SSL', 'installed_hosts');
            $dnsPayload = filled($site->primary_domain)
                ? $this->client->requestApi2($server, 'ZoneEdit', 'fetchzone_records', [
                    'domain' => $site->primary_domain,
                    'customonly' => 0,
                ])
                : [];

            $snapshot = [
                'source' => 'cpanel',
                'synced_at' => now()->toIso8601String(),
                'account' => [
                    'username' => $this->cpanelUsername($server),
                    'server_name' => $server->name,
                    'server_reference' => $server->provider_reference,
                ],
                'domains' => [
                    'main' => $this->normalizeMainDomain(data_get($domainsPayload, 'main_domain') ?? data_get($domainsPayload, 'domain') ?? $site->primary_domain),
                    'addon_domains' => $this->normalizeDomainList(data_get($domainsPayload, 'addon_domains') ?? []),
                    'subdomains' => $this->normalizeDomainList(data_get($domainsPayload, 'sub_domains') ?? data_get($domainsPayload, 'subdomains') ?? []),
                    'parked_domains' => $this->normalizeDomainList(data_get($domainsPayload, 'parked_domains') ?? data_get($domainsPayload, 'alias_domains') ?? []),
                ],
                'dns' => [
                    'domain' => $site->primary_domain,
                    'records' => $this->normalizeDnsRecords(data_get($dnsPayload, 'record') ?? data_get($dnsPayload, 'records') ?? data_get($dnsPayload, 'data') ?? []),
                ],
                'ssl' => [
                    'hosts' => $this->normalizeSslHosts(data_get($sslPayload, 'hosts') ?? data_get($sslPayload, 'data') ?? []),
                ],
                'notes' => array_values(array_filter([
                    'Domain inventory is sourced from cPanel UAPI.',
                    'DNS inventory is only fetched when a primary domain is configured.',
                    'SSL inventory is read-only and does not change certificate state.',
                ])),
            ];

            $site->forceFill([
                'live_configuration_snapshot' => $snapshot,
                'live_configuration_synced_at' => now(),
                'live_configuration_last_error' => null,
            ])->save();

            return [
                'Fetched live domain inventory from cPanel.',
                sprintf('Fetched %d SSL host%s.', count($snapshot['ssl']['hosts']), count($snapshot['ssl']['hosts']) === 1 ? '' : 's'),
                filled($site->primary_domain)
                    ? sprintf('Fetched %d DNS record%s for %s.', count($snapshot['dns']['records']), count($snapshot['dns']['records']) === 1 ? '' : 's', $site->primary_domain)
                    : 'Skipped DNS inventory because no primary domain is configured.',
                'Stored the normalized inventory snapshot on the site record.',
            ];
        } catch (Throwable $throwable) {
            $site->forceFill([
                'live_configuration_last_error' => $throwable->getMessage(),
            ])->save();

            throw $throwable;
        }
    }

    protected function isSupported(?Server $server): bool
    {
        return (bool) $server
            && $server->connection_type === 'cpanel'
            && filled($server->effectiveCpanelApiToken());
    }

    protected function cpanelUsername(Server $server): string
    {
        return trim((string) ($server->effectiveCpanelUsername() ?: $server->effectiveSshUser()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDomainList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    $domain = trim($item);

                    return $domain === '' ? null : [
                        'domain' => $domain,
                        'root_domain' => null,
                        'document_root' => null,
                        'https_redirect' => null,
                        'raw' => null,
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

                return [
                    'domain' => (string) $domain,
                    'root_domain' => $this->firstFilled([
                        data_get($item, 'rootdomain'),
                        data_get($item, 'root_domain'),
                        data_get($item, 'parent_domain'),
                        data_get($item, 'topdomain'),
                    ]),
                    'document_root' => $this->firstFilled([
                        data_get($item, 'documentroot'),
                        data_get($item, 'document_root'),
                        data_get($item, 'dir'),
                        data_get($item, 'path'),
                    ]),
                    'https_redirect' => $this->firstFilled([
                        data_get($item, 'redirects_to_https'),
                        data_get($item, 'force_https_redirect'),
                        data_get($item, 'https_redirect'),
                        data_get($item, 'secure_redirect'),
                    ]),
                    'raw' => $item,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeMainDomain(mixed $item): ?array
    {
        if (is_string($item)) {
            $item = trim($item);

            return $item === '' ? null : [
                'domain' => $item,
                'document_root' => null,
                'raw' => null,
            ];
        }

        if (! is_array($item)) {
            return null;
        }

        $domain = $this->firstFilled([
            data_get($item, 'domain'),
            data_get($item, 'main_domain'),
            data_get($item, 'name'),
        ]);

        if (! filled($domain)) {
            return null;
        }

        return [
            'domain' => (string) $domain,
            'document_root' => $this->firstFilled([
                data_get($item, 'documentroot'),
                data_get($item, 'document_root'),
                data_get($item, 'dir'),
                data_get($item, 'path'),
            ]),
            'raw' => $item,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDnsRecords(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $name = $this->firstFilled([
                    data_get($item, 'name'),
                    data_get($item, 'Name'),
                    data_get($item, 'line'),
                ]);

                $type = $this->firstFilled([
                    data_get($item, 'type'),
                    data_get($item, 'Type'),
                ]);

                if (! filled($name) || ! filled($type)) {
                    return null;
                }

                return [
                    'name' => (string) $name,
                    'type' => (string) $type,
                    'content' => (string) $this->firstFilled([
                        data_get($item, 'record'),
                        data_get($item, 'address'),
                        data_get($item, 'cname'),
                        data_get($item, 'txtdata'),
                        data_get($item, 'exchange'),
                        data_get($item, 'target'),
                        data_get($item, 'value'),
                    ], ''),
                    'ttl' => $this->firstFilled([
                        data_get($item, 'ttl'),
                        data_get($item, 'TTL'),
                    ]),
                    'raw' => $item,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSslHosts(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $domain = $this->firstFilled([
                    data_get($item, 'domain'),
                    data_get($item, 'hostname'),
                    data_get($item, 'host'),
                    data_get($item, 'subject'),
                ]);

                if (! filled($domain)) {
                    return null;
                }

                return [
                    'domain' => (string) $domain,
                    'issuer' => $this->firstFilled([
                        data_get($item, 'issuer'),
                        data_get($item, 'issuer_dn'),
                        data_get($item, 'issuer_name'),
                    ]),
                    'not_after' => $this->firstFilled([
                        data_get($item, 'not_after'),
                        data_get($item, 'expires_on'),
                        data_get($item, 'expiry'),
                        data_get($item, 'valid_to'),
                    ]),
                    'installed' => (bool) $this->firstFilled([
                        data_get($item, 'installed'),
                        data_get($item, 'has_ssl'),
                        data_get($item, 'ssl_exists'),
                    ], false),
                    'raw' => $item,
                ];
            })
            ->filter()
            ->values()
            ->all();
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
