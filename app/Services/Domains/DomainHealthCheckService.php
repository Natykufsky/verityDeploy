<?php

namespace App\Services\Domains;

use App\Models\Domain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DomainHealthCheckService
{
    public function liveSiteUrl(Domain $domain): ?string
    {
        $site = $domain->site;

        if (! $site) {
            return null;
        }

        $host = data_get($site, 'currentDomain.name')
            ?: data_get($site, 'primary_domain')
            ?: $domain->name;

        $host = trim((string) $host);

        if ($host === '') {
            return null;
        }

        $scheme = ($site->force_https || in_array((string) $site->ssl_state, ['valid', 'issued', 'active', 'installed'], true))
            ? 'https'
            : 'http';

        $endpoint = filled($site->health_check_endpoint)
            ? '/'.ltrim((string) $site->health_check_endpoint, '/')
            : '';

        return sprintf('%s://%s%s', $scheme, $host, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Domain $domain): array
    {
        $site = $domain->site?->loadMissing('server', 'currentDomain');
        $url = $this->liveSiteUrl($domain);

        if (! filled($url)) {
            return [
                'supported' => false,
                'url' => null,
                'status_code' => null,
                'status' => 'unavailable',
                'status_label' => 'Not available',
                'message' => 'This domain is not linked to a site yet, so there is no live URL to check.',
                'hint' => 'Link the domain to a site first, then re-open the page to run a live probe.',
                'checked_at' => now()->format('M d, Y H:i'),
            ];
        }

        try {
            $response = Http::withoutVerifying()
                ->accept('text/html')
                ->timeout(15)
                ->connectTimeout(5)
                ->get($url);

            $statusCode = $response->status();
            $serverHeader = $response->header('Server');

            return [
                'supported' => true,
                'url' => $url,
                'status_code' => $statusCode,
                'status' => $this->statusForCode($statusCode),
                'status_label' => $this->statusLabelForCode($statusCode),
                'message' => $this->messageForCode($statusCode, $url),
                'hint' => $this->hintForCode($statusCode, $domain, $site),
                'server_header' => $serverHeader,
                'checked_at' => now()->format('M d, Y H:i'),
            ];
        } catch (ConnectionException $exception) {
            return [
                'supported' => true,
                'url' => $url,
                'status_code' => null,
                'status' => 'unreachable',
                'status_label' => 'Unreachable',
                'message' => 'The live site could not be reached from the dashboard.',
                'hint' => 'Check DNS, the vhost document root, and whether the server is online.',
                'error' => $exception->getMessage(),
                'checked_at' => now()->format('M d, Y H:i'),
            ];
        }
    }

    protected function statusForCode(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'healthy',
            $statusCode >= 300 && $statusCode < 400 => 'redirect',
            $statusCode === 401 => 'unauthorized',
            $statusCode === 403 => 'forbidden',
            $statusCode === 404 => 'not-found',
            $statusCode >= 500 => 'server-error',
            default => 'unexpected',
        };
    }

    protected function statusLabelForCode(int $statusCode): string
    {
        return match ($this->statusForCode($statusCode)) {
            'healthy' => 'Healthy',
            'redirect' => 'Redirecting',
            'unauthorized' => 'Unauthorized',
            'forbidden' => 'Forbidden',
            'not-found' => 'Not found',
            'server-error' => 'Server error',
            default => sprintf('HTTP %d', $statusCode),
        };
    }

    protected function messageForCode(int $statusCode, string $url): string
    {
        return match ($this->statusForCode($statusCode)) {
            'healthy' => sprintf('%s responded successfully.', $url),
            'redirect' => sprintf('%s redirected successfully.', $url),
            'unauthorized' => sprintf('%s returned 401 Unauthorized.', $url),
            'forbidden' => sprintf('%s returned 403 Forbidden.', $url),
            'not-found' => sprintf('%s returned 404 Not Found.', $url),
            'server-error' => sprintf('%s returned a 5xx server error.', $url),
            default => sprintf('%s returned HTTP %d.', $url, $statusCode),
        };
    }

    protected function hintForCode(int $statusCode, Domain $domain, ?object $site): string
    {
        return match ($this->statusForCode($statusCode)) {
            'healthy' => 'The document root and index file look reachable from the public web server.',
            'redirect' => 'The domain is redirecting normally. Follow the final target if you want the exact landing page.',
            'unauthorized' => 'The site is protected by auth or a rule that requires credentials.',
            'forbidden' => 'This is usually a wrong document root, missing index file, blocked permissions, or a LiteSpeed/.htaccess deny rule.',
            'not-found' => 'The web server can reach the host, but the target path or release may be missing.',
            'server-error' => 'The site likely has an application or PHP error at the document root.',
            default => 'Double-check the domain mapping, document root, and server access rules.',
        };
    }
}
