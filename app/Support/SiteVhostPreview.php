<?php

namespace App\Support;

use App\Models\Site;

class SiteVhostPreview
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Site $site): array
    {
        $domainPreview = $site->domain_preview;
        $server = $site->server;
        $supported = (bool) ($server?->can_manage_vhosts) && (($server?->connection_type ?? null) !== 'cpanel') && filled($site->primary_domain);
        $hostnames = array_values(array_filter($domainPreview['hostnames'] ?? []));
        $engine = static::recommendedEngine($server?->provider_type);
        $documentRoot = static::documentRoot($site);
        $configPath = filled($server?->vhost_config_path) ? (string) $server->vhost_config_path : static::configPath($site, $engine);
        $enabledPath = filled($server?->vhost_enabled_path) ? (string) $server->vhost_enabled_path : static::enabledPath($site, $engine);
        $reloadCommand = filled($server?->vhost_reload_command) ? (string) $server->vhost_reload_command : static::reloadCommand($engine);

        return [
            'supported' => $supported,
            'message' => $supported
                ? 'This preview shows the vhost block you can apply on a VPS-style server.'
                : 'Enable vhost management on the server to preview a VPS vhost layout. cPanel servers use the built-in domain mapping preview instead.',
            'engine' => $engine,
            'engine_label' => ucfirst($engine),
            'hostnames' => $hostnames,
            'document_root' => $documentRoot,
            'vhost_path' => $configPath,
            'enabled_path' => $enabledPath,
            'reload_command' => $reloadCommand,
            'ssl_state' => (string) ($site->ssl_state ?? 'unconfigured'),
            'force_https' => (bool) $site->force_https,
            'ssl_summary' => $site->ssl_summary,
            'force_https_summary' => $site->force_https_summary,
            'snippet' => static::buildSnippet($engine, $hostnames, $documentRoot, $configPath, (string) ($site->ssl_state ?? 'unconfigured'), (bool) $site->force_https),
            'steps' => static::buildSteps($engine, $documentRoot, $site->force_https, (string) ($site->ssl_state ?? 'unconfigured'), $configPath, $enabledPath, $reloadCommand),
        ];
    }

    protected static function recommendedEngine(?string $providerType): string
    {
        return in_array($providerType, ['aws', 'digitalocean', 'hetzner', 'vultr', 'linode', 'local', 'manual'], true)
            ? 'nginx'
            : 'apache';
    }

    protected static function documentRoot(Site $site): string
    {
        $basePath = filled($site->current_release_path)
            ? rtrim((string) $site->current_release_path, '/')
            : rtrim((string) $site->deploy_path, '/').'/current';

        $webRoot = trim((string) ($site->web_root ?: 'public'), '/');

        return rtrim($basePath, '/').'/'.$webRoot;
    }

    protected static function configPath(Site $site, string $engine): string
    {
        $slug = static::slugForSite($site);

        return $engine === 'apache'
            ? sprintf('/etc/apache2/sites-available/%s.conf', $slug)
            : sprintf('/etc/nginx/sites-available/%s.conf', $slug);
    }

    protected static function enabledPath(Site $site, string $engine): string
    {
        $slug = static::slugForSite($site);

        return $engine === 'apache'
            ? sprintf('/etc/apache2/sites-enabled/%s.conf', $slug)
            : sprintf('/etc/nginx/sites-enabled/%s.conf', $slug);
    }

    protected static function reloadCommand(string $engine): string
    {
        return $engine === 'apache'
            ? 'systemctl reload apache2 || systemctl reload httpd'
            : 'systemctl reload nginx';
    }

    protected static function slugForSite(Site $site): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($site->primary_domain ?: $site->name ?: 'site'))) ?: 'site';
    }

    /**
     * @param  array<int, string>  $hostnames
     */
    protected static function buildSnippet(string $engine, array $hostnames, string $documentRoot, string $configPath, string $sslState, bool $forceHttps): string
    {
        $hostnames = array_values(array_filter($hostnames));
        $primaryHost = $hostnames[0] ?? 'example.test';
        $serverNames = implode(' ', $hostnames ?: [$primaryHost]);

        if ($engine === 'apache') {
            $redirect = $forceHttps && in_array($sslState, ['valid', 'issued', 'active'], true)
                ? <<<'APACHE'
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

APACHE
                : '';

            return <<<APACHE
<VirtualHost *:80>
    ServerName {$primaryHost}
    ServerAlias {$serverNames}
    DocumentRoot "{$documentRoot}"

    <Directory "{$documentRoot}">
        AllowOverride All
        Require all granted
    </Directory>

{$redirect}</VirtualHost>
APACHE;
        }

        $redirect = $forceHttps && in_array($sslState, ['valid', 'issued', 'active'], true)
            ? <<<'NGINX'
    return 301 https://$host$request_uri;

NGINX
            : '';

        return <<<NGINX
server {
    listen 80;
    server_name {$serverNames};
    root {$documentRoot};
    index index.php index.html;

{$redirect}    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
NGINX;
    }

    /**
     * @return array<int, string>
     */
    protected static function buildSteps(string $engine, string $documentRoot, bool $forceHttps, string $sslState, string $configPath, string $enabledPath, string $reloadCommand): array
    {
        $steps = [
            sprintf('write the %s vhost file to %s', $engine, $configPath),
            sprintf('set the document root to %s', $documentRoot),
        ];

        if ($engine === 'apache') {
            $steps[] = sprintf('enable the site from %s', $enabledPath);
        } else {
            $steps[] = sprintf('link the site into %s', $enabledPath);
        }

        if ($forceHttps) {
            $steps[] = in_array($sslState, ['valid', 'issued', 'active'], true)
                ? 'add the https redirect block'
                : 'issue ssl before enabling the https redirect';
        }

        $steps[] = sprintf('run %s after saving the config', $reloadCommand);

        return $steps;
    }
}
