<?php

namespace App\Services\Domains;

use App\Models\Domain;
use App\Models\Server;
use App\Services\Alerts\OperationalAlertService;
use Carbon\CarbonInterface;

class DomainSslManagementService
{
    public function __construct(protected OperationalAlertService $alerts) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Domain $domain): array
    {
        $expiresAt = $domain->ssl_expires_at;
        $daysRemaining = filled($expiresAt) ? now()->diffInDays($expiresAt, false) : null;

        return [
            'supported' => true,
            'is_ssl_enabled' => (bool) $domain->is_ssl_enabled,
            'ssl_status' => (string) ($domain->ssl_status ?: 'unconfigured'),
            'ssl_summary' => $this->sslSummary($domain),
            'ssl_expires_at' => $expiresAt?->format('M d, Y H:i') ?? 'not set',
            'days_remaining' => $daysRemaining,
            'renewal_status' => $this->renewalStatus($domain),
            'renewal_summary' => $this->renewalSummary($domain),
            'certificate_present' => filled($domain->ssl_certificate),
            'chain_present' => filled($domain->ssl_chain),
            'key_present' => filled($domain->ssl_key),
            'steps' => [
                'Store the certificate, private key, and chain on the domain record.',
                'Update the SSL status and expiry date when a new certificate is issued.',
                'Track renewal timing so expiring certificates are easier to spot.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function markIssued(Domain $domain, ?CarbonInterface $expiresAt = null): array
    {
        $expiresAt ??= now()->addDays(90);

        $domain->update([
            'is_ssl_enabled' => true,
            'ssl_status' => 'issued',
            'ssl_expires_at' => $expiresAt,
        ]);

        return [
            sprintf('Marked %s as SSL issued.', $domain->name),
            sprintf('SSL expiry is tracked until %s.', $expiresAt->format('M d, Y H:i')),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function markRenewalDue(Domain $domain): array
    {
        $domain->update([
            'is_ssl_enabled' => true,
            'ssl_status' => 'pending',
        ]);

        return [
            sprintf('Marked %s as needing SSL renewal.', $domain->name),
            'The domain will now show a renewal-due status in the SSL tab.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function clearTracking(Domain $domain): array
    {
        $domain->update([
            'is_ssl_enabled' => false,
            'ssl_status' => null,
            'ssl_expires_at' => null,
            'ssl_certificate' => null,
            'ssl_key' => null,
            'ssl_chain' => null,
        ]);

        return [
            sprintf('Cleared SSL tracking for %s.', $domain->name),
            'Certificate data and renewal dates were removed from the domain record.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function scanServer(Server $server): array
    {
        if ($server->domains()->count() === 0) {
            return ['No domains were available to scan for SSL renewals.'];
        }

        $summary = [];
        $expiringSoon = 0;
        $expired = 0;

        $server->loadMissing(['domains' => fn ($query) => $query->orderBy('name')]);

        foreach ($server->domains as $domain) {
            if (! (bool) $domain->is_ssl_enabled || blank($domain->ssl_expires_at)) {
                continue;
            }

            $daysRemaining = now()->diffInDays($domain->ssl_expires_at, false);

            if ($daysRemaining < 0) {
                $expired++;
                $domain->updateQuietly([
                    'ssl_status' => 'expired',
                ]);

                $this->alerts->notifyAll(
                    sprintf('SSL expired: %s', $domain->name),
                    sprintf('The SSL certificate for %s expired on %s.', $domain->name, $domain->ssl_expires_at->format('M d, Y H:i')),
                    'danger',
                    null,
                    [
                        'server_id' => $server->id,
                        'domain_id' => $domain->id,
                        'domain_name' => $domain->name,
                        'ssl_expires_at' => $domain->ssl_expires_at->toIso8601String(),
                    ],
                );

                continue;
            }

            if ($daysRemaining <= 30) {
                $expiringSoon++;
                $this->alerts->notifyAll(
                    sprintf('SSL expiring soon: %s', $domain->name),
                    sprintf(
                        '%s expires in %d day%s on %s.',
                        $domain->name,
                        $daysRemaining,
                        $daysRemaining === 1 ? '' : 's',
                        $domain->ssl_expires_at->format('M d, Y H:i'),
                    ),
                    'warning',
                    null,
                    [
                        'server_id' => $server->id,
                        'domain_id' => $domain->id,
                        'domain_name' => $domain->name,
                        'ssl_expires_at' => $domain->ssl_expires_at->toIso8601String(),
                    ],
                );
            }
        }

        $summary[] = sprintf(
            '%d SSL certificate%s %s expiring within 30 days.',
            $expiringSoon,
            $expiringSoon === 1 ? '' : 's',
            $expiringSoon === 1 ? 'is' : 'are',
        );
        $summary[] = sprintf(
            '%d SSL certificate%s %s already expired.',
            $expired,
            $expired === 1 ? '' : 's',
            $expired === 1 ? 'is' : 'are',
        );

        return $summary;
    }

    protected function sslSummary(Domain $domain): string
    {
        return match ((string) ($domain->ssl_status ?: 'unconfigured')) {
            'issued' => 'The domain has a certificate on file and should continue to serve HTTPS.',
            'pending' => 'The domain has SSL enabled but renewal or issuance is still in progress.',
            'expired' => 'The current certificate has expired and should be renewed.',
            'failed' => 'The last SSL attempt failed and should be retried.',
            default => 'No SSL certificate state has been tracked for this domain yet.',
        };
    }

    protected function renewalStatus(Domain $domain): string
    {
        if (! (bool) $domain->is_ssl_enabled) {
            return 'disabled';
        }

        if (blank($domain->ssl_expires_at)) {
            return 'unknown';
        }

        $daysRemaining = now()->diffInDays($domain->ssl_expires_at, false);

        if ($daysRemaining < 0) {
            return 'expired';
        }

        if ($daysRemaining <= 30) {
            return 'renewal due';
        }

        return 'healthy';
    }

    protected function renewalSummary(Domain $domain): string
    {
        return match ($this->renewalStatus($domain)) {
            'healthy' => 'The certificate is not due for renewal yet.',
            'renewal due' => 'The certificate is close to expiry and should be renewed soon.',
            'expired' => 'The certificate is already expired and needs immediate attention.',
            'disabled' => 'SSL tracking is disabled for this domain.',
            default => 'The certificate expiry date has not been tracked yet.',
        };
    }
}
