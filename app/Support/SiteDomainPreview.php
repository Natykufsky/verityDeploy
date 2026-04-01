<?php

namespace App\Support;

class SiteDomainPreview
{
    /**
     * Build a preview payload for the Site domain editor.
     *
     * @param  array<int, mixed>  $subdomains
     * @param  array<int, mixed>  $aliasDomains
     * @return array<string, mixed>
     */
    public static function build(
        ?string $primaryDomain,
        array $subdomains = [],
        array $aliasDomains = [],
        ?string $connectionType = null,
        ?string $deployPath = null,
        ?string $webRoot = null,
        ?string $sslState = null,
        bool $forceHttps = false,
    ): array {
        $primaryDomain = static::normalizeHost($primaryDomain);
        $subdomains = static::normalizeHosts($subdomains);
        $aliasDomains = static::normalizeHosts($aliasDomains);
        $hosts = array_values(array_filter(array_unique(array_filter([
            $primaryDomain,
            ...$subdomains,
            ...$aliasDomains,
        ]))));

        $status = filled($primaryDomain) ? 'ready' : 'needs setup';
        $sslState = filled($sslState) ? strtolower(trim((string) $sslState)) : 'unconfigured';

        return [
            'status' => $status,
            'message' => filled($primaryDomain)
                ? 'These domains can be mapped to the site and pointed at the deployment target.'
                : 'Set a primary domain first so the domain map can be generated.',
            'primary_domain' => $primaryDomain,
            'subdomains' => $subdomains,
            'alias_domains' => $aliasDomains,
            'ssl_state' => $sslState,
            'ssl_badge' => static::sslBadge($sslState),
            'ssl_summary' => static::sslSummary($sslState),
            'force_https' => $forceHttps,
            'force_https_badge' => $forceHttps ? 'enabled' : 'disabled',
            'force_https_summary' => $forceHttps
                ? 'HTTP requests should redirect to HTTPS once SSL is valid.'
                : 'HTTP requests remain available until you enable HTTPS enforcement.',
            'hostnames' => $hosts,
            'host_count' => count($hosts),
            'subdomain_count' => count($subdomains),
            'alias_count' => count($aliasDomains),
            'deploy_path' => $deployPath,
            'web_root' => $webRoot,
            'connection_type' => $connectionType,
            'target_summary' => static::buildTargetSummary($connectionType, $deployPath, $webRoot),
            'config_hint' => static::buildConfigHint($connectionType, $deployPath, $webRoot, $sslState, $forceHttps),
            'config_steps' => static::buildConfigSteps($primaryDomain, $subdomains, $aliasDomains, $deployPath, $webRoot, $sslState, $forceHttps),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    protected static function normalizeHosts(array $items): array
    {
        $hosts = [];

        foreach ($items as $item) {
            $candidate = is_array($item)
                ? ($item['value'] ?? $item['domain'] ?? $item['name'] ?? $item['host'] ?? null)
                : $item;

            $candidate = static::normalizeHost($candidate);

            if ($candidate !== null) {
                $hosts[] = $candidate;
            }
        }

        return array_values(array_unique($hosts));
    }

    protected static function normalizeHost(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return strtolower($value);
    }

    protected static function buildTargetSummary(?string $connectionType, ?string $deployPath, ?string $webRoot): string
    {
        $path = filled($deployPath) ? $deployPath : 'deployment path not set';
        $root = filled($webRoot) ? $webRoot : 'web root not set';

        return match ($connectionType) {
            'cpanel' => sprintf('cPanel can map these hosts to %s with document root %s.', $path, $root),
            'local' => sprintf('Local deployments can preview the host map before the package is uploaded to %s.', $path),
            default => sprintf('The host map can point to %s with document root %s.', $path, $root),
        };
    }

    protected static function buildConfigHint(?string $connectionType, ?string $deployPath, ?string $webRoot, string $sslState, bool $forceHttps): string
    {
        $path = filled($deployPath) ? $deployPath : 'your deployment path';
        $root = filled($webRoot) ? $webRoot : 'public';

        $base = match ($connectionType) {
            'cpanel' => sprintf('Use the cPanel add-on or subdomain tools to point these hosts at %s / %s.', $path, $root),
            'local' => sprintf('Preview the config locally before shipping it to the remote host at %s.', $path),
            default => sprintf('A web server vhost can point these hosts at %s / %s.', $path, $root),
        };

        if ($forceHttps && in_array($sslState, ['valid', 'issued', 'active'], true)) {
            return $base.' HTTPS can be enforced now that SSL is ready.';
        }

        if ($forceHttps) {
            return $base.' Finish SSL before forcing HTTPS redirects.';
        }

        return $base;
    }

    /**
     * @param  array<int, string>  $subdomains
     * @param  array<int, string>  $aliasDomains
     * @return array<int, string>
     */
    protected static function buildConfigSteps(
        ?string $primaryDomain,
        array $subdomains,
        array $aliasDomains,
        ?string $deployPath,
        ?string $webRoot,
        ?string $sslState = null,
        bool $forceHttps = false,
    ): array {
        $steps = [];

        if (filled($primaryDomain)) {
            $steps[] = sprintf('set the primary domain to %s', $primaryDomain);
        }

        if (filled($subdomains)) {
            $steps[] = sprintf('map %d subdomain%s', count($subdomains), count($subdomains) === 1 ? '' : 's');
        }

        if (filled($aliasDomains)) {
            $steps[] = sprintf('add %d alias domain%s', count($aliasDomains), count($aliasDomains) === 1 ? '' : 's');
        }

        $path = filled($deployPath) ? $deployPath : 'the deployment path';
        $root = filled($webRoot) ? $webRoot : 'public';
        $steps[] = sprintf('point the document root at %s / %s', $path, $root);

        if ($forceHttps) {
            $steps[] = in_array(strtolower((string) $sslState), ['valid', 'issued', 'active'], true)
                ? 'add the https redirect once ssl is ready'
                : 'finish ssl before forcing https redirects';
        }

        return $steps;
    }

    protected static function sslBadge(string $sslState): string
    {
        return match ($sslState) {
            'valid', 'issued', 'active' => 'ssl ready',
            'pending' => 'ssl pending',
            'expired' => 'ssl expired',
            'failed' => 'ssl failed',
            default => 'ssl unconfigured',
        };
    }

    protected static function sslSummary(string $sslState): string
    {
        return match ($sslState) {
            'valid', 'issued', 'active' => 'SSL is ready for use on this domain map.',
            'pending' => 'SSL provisioning is in progress or waiting on issuance.',
            'expired' => 'The current certificate has expired and should be renewed.',
            'failed' => 'The last SSL attempt failed and needs attention.',
            default => 'SSL has not been configured for this site yet.',
        };
    }
}
