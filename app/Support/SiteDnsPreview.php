<?php

namespace App\Support;

use App\Models\Site;

class SiteDnsPreview
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Site $site): array
    {
        $server = $site->server;
        $records = [];
        $origin = $server?->ip_address ?: $server?->host ?: '127.0.0.1';
        $proxy = (bool) ($server?->dns_proxy_records ?? true);
        $supported = ($server?->dns_provider ?? 'manual') === 'cloudflare' && filled($server?->dns_api_token) && (bool) ($server?->can_manage_dns ?? false);

        if (filled($site->primary_domain)) {
            $records[] = [
                'type' => 'A',
                'name' => $site->primary_domain,
                'content' => $origin,
                'proxied' => $proxy,
            ];
        }

        foreach ((array) ($site->subdomains ?? []) as $subdomain) {
            $name = trim((string) (is_array($subdomain) ? ($subdomain['value'] ?? '') : $subdomain));

            if ($name !== '') {
                $records[] = [
                    'type' => 'A',
                    'name' => $name,
                    'content' => $origin,
                    'proxied' => $proxy,
                ];
            }
        }

        foreach ((array) ($site->alias_domains ?? []) as $aliasDomain) {
            $name = trim((string) (is_array($aliasDomain) ? ($aliasDomain['value'] ?? '') : $aliasDomain));

            if ($name !== '') {
                $records[] = [
                    'type' => 'A',
                    'name' => $name,
                    'content' => $origin,
                    'proxied' => $proxy,
                ];
            }
        }

        $records = collect($records)
            ->unique(fn (array $record): string => strtolower($record['type'].'|'.$record['name'].'|'.$record['content']))
            ->values()
            ->all();

        return [
            'supported' => $supported,
            'provider' => $server?->dns_provider_label ?? 'Cloudflare',
            'message' => $supported
                ? 'This preview shows the DNS records Cloudflare can create or update for the site.'
                : 'Enable Cloudflare DNS management on the server to preview the DNS records.',
            'zone_id' => $server?->dns_zone_id,
            'primary_domain' => $site->primary_domain,
            'records' => $records,
            'proxy' => $proxy,
            'steps' => [
                'Resolve the Cloudflare zone for the primary domain.',
                sprintf('Create or update %d DNS record%s.', count($records), count($records) === 1 ? '' : 's'),
                sprintf('Use %s proxy mode for the resolved records.', $proxy ? 'proxied' : 'dns-only'),
            ],
        ];
    }
}
