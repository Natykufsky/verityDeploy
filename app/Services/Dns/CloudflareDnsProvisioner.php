<?php

namespace App\Services\Dns;

use App\Models\Server;
use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareDnsProvisioner
{
    /**
     * @return array<string, mixed>
     */
    public function preview(Site $site): array
    {
        $server = $site->server;
        $primaryDomain = trim((string) $site->primary_domain);
        $records = $this->buildRecords($site);
        $supported = $this->isSupported($server);

        return [
            'supported' => $supported,
            'provider' => $server?->dns_provider_label ?? 'Cloudflare',
            'message' => $supported
                ? 'This preview shows the DNS records Cloudflare will create or update for the site.'
                : 'Enable Cloudflare DNS management on the server to preview the DNS record layout.',
            'zone_id' => $server?->effectiveDnsZoneId(),
            'primary_domain' => $primaryDomain,
            'records' => $records,
            'proxy' => $server ? $server->effectiveDnsProxyRecords() : true,
            'steps' => [
                sprintf('Resolve the Cloudflare zone for %s.', $primaryDomain ?: 'the primary domain'),
                sprintf('Create or update %d DNS record%s.', count($records), count($records) === 1 ? '' : 's'),
                sprintf('Use %s proxy mode for the resolved records.', ($server ? $server->effectiveDnsProxyRecords() : true) ? 'proxied' : 'dns-only'),
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

        if (! $this->isSupported($server)) {
            throw new RuntimeException('Cloudflare DNS management is not configured for this server.');
        }

        if (blank($site->primary_domain)) {
            throw new RuntimeException('The site does not have a primary domain configured.');
        }

        $zoneId = $this->resolveZoneId($server, $site->primary_domain);
        $records = $this->buildRecords($site);
        $summary = [];

        foreach ($records as $record) {
            $this->upsertRecord(
                $server,
                $zoneId,
                $record['type'],
                $record['name'],
                $record['content'],
                $record['proxied'],
            );

            $summary[] = sprintf(
                'Provisioned %s record for %s -> %s.',
                $record['type'],
                $record['name'],
                $record['content'],
            );
        }

        return $summary;
    }

    protected function isSupported(?Server $server): bool
    {
        return (bool) $server
            && ($server->effectiveDnsProvider() === 'cloudflare')
            && filled($server->effectiveDnsApiToken())
            && ($server->can_manage_dns ?? false);
    }

    /**
     * @return array<int, array{type: string, name: string, content: string, proxied: bool}>
     */
    protected function buildRecords(Site $site): array
    {
        $server = $site->server;
        $origin = $server?->ip_address ?: $server?->host ?: '127.0.0.1';
        $proxy = $server ? $server->effectiveDnsProxyRecords() : true;
        $records = [];

        $primaryDomain = trim((string) $site->primary_domain);

        if (filled($primaryDomain)) {
            $records[] = [
                'type' => 'A',
                'name' => $primaryDomain,
                'content' => $origin,
                'proxied' => $proxy,
            ];
        }

        foreach ((array) ($site->subdomains ?? []) as $subdomain) {
            $name = trim((string) (is_array($subdomain) ? ($subdomain['value'] ?? '') : $subdomain));

            if ($name === '') {
                continue;
            }

            $records[] = [
                'type' => 'A',
                'name' => $name,
                'content' => $origin,
                'proxied' => $proxy,
            ];
        }

        foreach ((array) ($site->alias_domains ?? []) as $aliasDomain) {
            $name = trim((string) (is_array($aliasDomain) ? ($aliasDomain['value'] ?? '') : $aliasDomain));

            if ($name === '') {
                continue;
            }

            $records[] = [
                'type' => 'A',
                'name' => $name,
                'content' => $origin,
                'proxied' => $proxy,
            ];
        }

        return collect($records)
            ->unique(fn (array $record): string => strtolower($record['type'].'|'.$record['name'].'|'.$record['content']))
            ->values()
            ->all();
    }

    protected function resolveZoneId(Server $server, string $domain): string
    {
        if (filled($server->effectiveDnsZoneId())) {
            return (string) $server->effectiveDnsZoneId();
        }

        $response = $this->client($server)
            ->get('zones', [
                'name' => $domain,
                'status' => 'active',
                'per_page' => 1,
            ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $zoneId = data_get($payload, 'result.0.id') ?? data_get($payload, 'result.0.zone_id');

        if (! filled($zoneId)) {
            throw new RuntimeException(sprintf('No Cloudflare zone was found for %s.', $domain));
        }

        return (string) $zoneId;
    }

    protected function upsertRecord(Server $server, string $zoneId, string $type, string $name, string $content, bool $proxied): void
    {
        $recordsResponse = $this->client($server)
            ->get(sprintf('zones/%s/dns_records', $zoneId), [
                'type' => $type,
                'name' => $name,
            ]);

        $recordsPayload = $recordsResponse->json();

        if ($recordsResponse->failed() || ! is_array($recordsPayload)) {
            throw new RuntimeException($this->errorMessage($recordsResponse));
        }

        $existing = data_get($recordsPayload, 'result.0');

        $body = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => 1,
            'proxied' => $proxied,
        ];

        if (is_array($existing) && filled(data_get($existing, 'id'))) {
            $response = $this->client($server)
                ->patch(sprintf('zones/%s/dns_records/%s', $zoneId, data_get($existing, 'id')), $body);
        } else {
            $response = $this->client($server)
                ->post(sprintf('zones/%s/dns_records', $zoneId), $body);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }
    }

    protected function client(Server $server)
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4')
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withToken((string) $server->effectiveDnsApiToken());
    }

    protected function errorMessage(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            $errors = data_get($payload, 'errors') ?? data_get($payload, 'messages') ?? [];

            if (is_array($errors) && filled($errors)) {
                return implode(' ', array_map(static fn ($error): string => is_array($error) ? (string) data_get($error, 'message', '') : (string) $error, $errors));
            }

            $message = data_get($payload, 'message');

            if (filled($message)) {
                return (string) $message;
            }
        }

        $message = trim($response->body());

        return $message !== '' ? $message : 'Unable to reach the Cloudflare API.';
    }
}
