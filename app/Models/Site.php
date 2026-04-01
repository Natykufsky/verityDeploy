<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use App\Services\AppSettings;
use App\Support\SiteDnsPreview;
use App\Support\SiteDomainPreview;
use App\Support\SiteVhostPreview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'team_id',
        'name',
        'repository_url',
        'default_branch',
        'deploy_path',
        'primary_domain',
        'subdomains',
        'alias_domains',
        'ssl_state',
        'force_https',
        'ssl_last_synced_at',
        'ssl_last_error',
        'current_release_path',
        'local_source_path',
        'ignore_local_source_ignored_files',
        'php_version',
        'web_root',
        'deploy_source',
        'environment_variables',
        'shared_env_contents',
        'shared_files',
        'github_webhook_id',
        'github_webhook_status',
        'github_webhook_synced_at',
        'github_webhook_last_error',
        'github_credential_profile_id',
        'dns_credential_profile_id',
        'webhook_credential_profile_id',
        'live_configuration_snapshot',
        'live_configuration_synced_at',
        'live_configuration_last_error',
        'vhost_apply_last_run_at',
        'vhost_apply_last_output',
        'vhost_apply_last_error',
        'vhost_apply_last_steps',
        'active',
        'last_deployed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shared_env_contents' => EncryptedTextOrPlain::class,
            'environment_variables' => 'array',
            'shared_files' => 'array',
            'subdomains' => 'array',
            'alias_domains' => 'array',
            'ssl_state' => 'string',
            'force_https' => 'boolean',
            'ssl_last_synced_at' => 'datetime',
            'ssl_last_error' => 'string',
            'github_webhook_synced_at' => 'datetime',
            'live_configuration_snapshot' => 'array',
            'live_configuration_synced_at' => 'datetime',
            'live_configuration_last_error' => 'string',
            'github_credential_profile_id' => 'integer',
            'dns_credential_profile_id' => 'integer',
            'webhook_credential_profile_id' => 'integer',
            'vhost_apply_last_run_at' => 'datetime',
            'vhost_apply_last_output' => 'string',
            'vhost_apply_last_error' => 'string',
            'vhost_apply_last_steps' => 'array',
            'ignore_local_source_ignored_files' => 'boolean',
            'active' => 'boolean',
            'last_deployed_at' => 'datetime',
            'current_release_path' => 'string',
            'local_source_path' => 'string',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function githubCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'github_credential_profile_id');
    }

    public function dnsCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'dns_credential_profile_id');
    }

    public function webhookCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'webhook_credential_profile_id');
    }

    public function effectiveWebhookSecret(): ?string
    {
        $profile = $this->webhookCredentialProfile ?: app(AppSettings::class)->defaultWebhookCredentialProfile();

        if (! $profile) {
            return null;
        }

        return data_get($profile->settings, 'webhook_secret')
            ?? data_get($profile->settings, 'secret');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function cpanelWizardRuns(): HasMany
    {
        return $this->hasMany(CpanelWizardRun::class)->latest('started_at');
    }

    public function releaseCleanupRuns(): HasMany
    {
        return $this->hasMany(ReleaseCleanupRun::class)->latest('started_at');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(SiteBackup::class)->latest('started_at');
    }

    public function terminalRuns(): HasMany
    {
        return $this->hasMany(SiteTerminalRun::class)->latest('started_at');
    }

    public function latestDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->latestOfMany();
    }

    /**
     * @return Builder<self>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->whereHas('server', function (Builder $query) use ($user): void {
                $query->accessibleTo($user);
            })->orWhere(function (Builder $query) use ($user): void {
                $query->whereNotNull('team_id')
                    ->whereIn('team_id', $user->teams()->select('teams.id'));
            });
        });
    }

    public function getCurrentReleaseStatusAttribute(): string
    {
        return filled($this->current_release_path) ? 'active' : 'inactive';
    }

    public function getSharedEnvModeAttribute(): string
    {
        return filled($this->shared_env_contents) ? 'custom' : 'generated';
    }

    public function getSharedEnvSummaryAttribute(): string
    {
        return match ($this->shared_env_mode) {
            'custom' => 'This site uses a custom shared .env file override instead of generated environment variables.',
            default => 'This site generates its shared .env file from the environment variables editor.',
        };
    }

    public function getSharedEnvBadgeAttribute(): string
    {
        return match ($this->shared_env_mode) {
            'custom' => 'custom override',
            default => 'generated',
        };
    }

    public function getDomainStatusAttribute(): string
    {
        return filled($this->primary_domain) ? 'ready' : 'needs setup';
    }

    public function getSslBadgeAttribute(): string
    {
        return match ((string) ($this->ssl_state ?: 'unconfigured')) {
            'valid', 'issued', 'active' => 'ssl ready',
            'pending' => 'ssl pending',
            'expired' => 'ssl expired',
            'failed' => 'ssl failed',
            default => 'ssl unconfigured',
        };
    }

    public function getSslSummaryAttribute(): string
    {
        return match ((string) ($this->ssl_state ?: 'unconfigured')) {
            'valid', 'issued', 'active' => 'SSL is ready for use on this site.',
            'pending' => 'SSL provisioning is in progress or waiting on issuance.',
            'expired' => 'The current certificate has expired and should be renewed.',
            'failed' => 'The last SSL attempt failed and needs attention.',
            default => 'SSL has not been configured for this site yet.',
        };
    }

    public function getSslLastSyncedBadgeAttribute(): string
    {
        return filled($this->ssl_last_synced_at)
            ? $this->ssl_last_synced_at->format('M d, Y H:i')
            : 'never synced';
    }

    public function getSslLastErrorSummaryAttribute(): string
    {
        return filled($this->ssl_last_error) ? (string) $this->ssl_last_error : 'No SSL errors recorded.';
    }

    public function getForceHttpsBadgeAttribute(): string
    {
        return $this->force_https ? 'https enforced' : 'http allowed';
    }

    public function getForceHttpsSummaryAttribute(): string
    {
        return $this->force_https
            ? 'HTTP requests should redirect to HTTPS once SSL is ready.'
            : 'HTTP requests are still allowed until you enable HTTPS enforcement.';
    }

    public function getDnsProviderLabelAttribute(): string
    {
        return $this->server?->dns_provider_label ?? 'manual';
    }

    public function getDnsProviderSummaryAttribute(): string
    {
        return $this->server?->dns_provider_summary ?? 'DNS is managed manually outside the app.';
    }

    public function getLiveConfigurationStatusAttribute(): string
    {
        if (filled($this->live_configuration_last_error)) {
            return 'error';
        }

        if (filled($this->live_configuration_snapshot)) {
            return 'synced';
        }

        return 'not synced';
    }

    public function getLiveConfigurationBadgeAttribute(): string
    {
        return match ($this->live_configuration_status) {
            'synced' => 'live',
            'error' => 'error',
            default => 'not synced',
        };
    }

    public function getLiveConfigurationSummaryAttribute(): string
    {
        return match ($this->live_configuration_status) {
            'synced' => 'The live cPanel inventory snapshot is available for this site.',
            'error' => 'The latest live inventory sync recorded an error and needs attention.',
            default => 'No live inventory snapshot has been synced yet.',
        };
    }

    public function getLiveConfigurationSyncedBadgeAttribute(): string
    {
        return filled($this->live_configuration_synced_at)
            ? $this->live_configuration_synced_at->format('M d, Y H:i')
            : 'never synced';
    }

    public function getLiveConfigurationErrorSummaryAttribute(): string
    {
        return filled($this->live_configuration_last_error)
            ? (string) $this->live_configuration_last_error
            : 'No live inventory errors recorded.';
    }

    public function getLiveConfigurationDriftStatusAttribute(): string
    {
        $snapshot = (array) ($this->live_configuration_snapshot ?? []);
        $source = strtolower((string) data_get($snapshot, 'source', ''));

        if (blank($snapshot)) {
            return 'not synced';
        }

        if ($source === 'cpanel') {
            $domainPreview = $this->domainPreviewFromSite();
            $dnsPreview = $this->dns_preview;
            $sslPreview = $this->ssl_preview;
            $liveDomains = (array) data_get($snapshot, 'domains', []);
            $liveDns = (array) data_get($snapshot, 'dns.records', []);
            $liveSsl = (array) data_get($snapshot, 'ssl.hosts', []);

            $mismatches = 0;

            if (filled($domainPreview['primary_domain'] ?? null) && filled(data_get($liveDomains, 'main.domain')) && data_get($liveDomains, 'main.domain') !== $domainPreview['primary_domain']) {
                $mismatches++;
            }

            $mismatches += abs(count((array) data_get($domainPreview, 'subdomains', [])) - count((array) data_get($liveDomains, 'subdomains', [])));
            $mismatches += abs(count((array) data_get($domainPreview, 'alias_domains', [])) - count((array) data_get($liveDomains, 'parked_domains', [])));
            $mismatches += abs(count((array) data_get($dnsPreview, 'records', [])) - count($liveDns));

            $expectedSslState = strtolower((string) data_get($sslPreview, 'ssl_state', $this->ssl_state ?: 'unconfigured'));
            $liveSslState = $this->liveSslStateFromSnapshot($liveSsl);
            if ($expectedSslState !== $liveSslState && ! ($expectedSslState === 'unconfigured' && $liveSslState === 'unknown')) {
                $mismatches++;
            }

            return $mismatches > 0 ? 'drift' : 'in sync';
        }

        if ($source === 'vps') {
            $expected = $this->vhostPreviewFromSite();
            $live = (array) data_get($snapshot, 'live', []);
            $expectedHosts = array_map('strtolower', array_values(array_filter((array) data_get($expected, 'hostnames', []))));
            $liveHosts = array_map('strtolower', array_values(array_filter((array) data_get($live, 'hostnames', []))));
            $expectedRoot = strtolower((string) data_get($expected, 'document_root', ''));
            $liveRoot = strtolower((string) data_get($live, 'document_root', ''));

            $mismatches = count(array_diff($expectedHosts, $liveHosts))
                + count(array_diff($liveHosts, $expectedHosts))
                + (($expectedRoot !== '' && $liveRoot !== '' && $expectedRoot !== $liveRoot) ? 1 : 0);

            return $mismatches > 0 ? 'drift' : 'in sync';
        }

        return 'unknown';
    }

    public function getLiveConfigurationDriftBadgeAttribute(): string
    {
        return match ($this->live_configuration_drift_status) {
            'in sync' => 'in sync',
            'drift' => 'drift',
            'not synced' => 'not synced',
            default => 'unknown',
        };
    }

    public function getLiveConfigurationDriftSummaryAttribute(): string
    {
        return match ($this->live_configuration_drift_status) {
            'in sync' => 'The live configuration matches the site intent closely.',
            'drift' => 'The live configuration differs from the site intent and should be reviewed.',
            'not synced' => 'Sync live inventory first to compare the server state.',
            default => 'The drift state could not be determined.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getLiveConfigurationDriftAttribute(): array
    {
        $snapshot = (array) ($this->live_configuration_snapshot ?? []);
        $source = strtolower((string) data_get($snapshot, 'source', ''));

        if (blank($snapshot)) {
            return [
                'source' => $source ?: 'unknown',
                'status' => 'not synced',
                'summary' => 'Sync live inventory first to compare the server state.',
                'sections' => [],
            ];
        }

        if ($source === 'cpanel') {
            $domainPreview = $this->domainPreviewFromSite();
            $dnsPreview = $this->dns_preview;
            $sslPreview = $this->ssl_preview;
            $liveDomains = (array) data_get($snapshot, 'domains', []);
            $liveDns = (array) data_get($snapshot, 'dns.records', []);
            $liveSsl = (array) data_get($snapshot, 'ssl.hosts', []);

            $sections = [
                [
                    'label' => 'Primary domain',
                    'expected' => filled($domainPreview['primary_domain'] ?? null) ? $domainPreview['primary_domain'] : 'not set',
                    'actual' => data_get($liveDomains, 'main.domain') ?? 'not synced',
                ],
                [
                    'label' => 'Subdomains',
                    'expected' => (string) count((array) data_get($domainPreview, 'subdomains', [])),
                    'actual' => (string) count((array) data_get($liveDomains, 'subdomains', [])),
                ],
                [
                    'label' => 'Alias domains',
                    'expected' => (string) count((array) data_get($domainPreview, 'alias_domains', [])),
                    'actual' => (string) count((array) data_get($liveDomains, 'parked_domains', [])),
                ],
                [
                    'label' => 'DNS records',
                    'expected' => (string) count((array) data_get($dnsPreview, 'records', [])),
                    'actual' => (string) count($liveDns),
                ],
                [
                    'label' => 'SSL state',
                    'expected' => (string) data_get($sslPreview, 'ssl_badge', $this->ssl_badge),
                    'actual' => (string) $this->liveSslStateFromSnapshot($liveSsl),
                ],
            ];

            return [
                'source' => $source,
                'status' => $this->live_configuration_drift_status,
                'summary' => $this->live_configuration_drift_summary,
                'sections' => $sections,
            ];
        }

        if ($source === 'vps') {
            $expected = $this->vhostPreviewFromSite();
            $live = (array) data_get($snapshot, 'live', []);

            $sections = [
                [
                    'label' => 'Engine',
                    'expected' => (string) data_get($expected, 'engine_label', 'nginx'),
                    'actual' => (string) data_get($live, 'engine', 'unknown'),
                ],
                [
                    'label' => 'Hostnames',
                    'expected' => (string) count((array) data_get($expected, 'hostnames', [])),
                    'actual' => (string) count((array) data_get($live, 'hostnames', [])),
                ],
                [
                    'label' => 'Document root',
                    'expected' => (string) data_get($expected, 'document_root', 'n/a'),
                    'actual' => (string) data_get($live, 'document_root', 'n/a'),
                ],
                [
                    'label' => 'SSL state',
                    'expected' => (string) data_get($expected, 'ssl_state', 'unconfigured'),
                    'actual' => (string) data_get($live, 'ssl_state', 'unconfigured'),
                ],
                [
                    'label' => 'HTTPS',
                    'expected' => data_get($expected, 'force_https') ? 'enabled' : 'disabled',
                    'actual' => data_get($live, 'force_https') ? 'enabled' : 'disabled',
                ],
            ];

            return [
                'source' => $source,
                'status' => $this->live_configuration_drift_status,
                'summary' => $this->live_configuration_drift_summary,
                'sections' => $sections,
            ];
        }

        return [
            'source' => $source ?: 'unknown',
            'status' => 'unknown',
            'summary' => 'The drift state could not be determined.',
            'sections' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function domainPreviewFromSite(): array
    {
        return SiteDomainPreview::build(
            $this->primary_domain,
            (array) ($this->subdomains ?? []),
            (array) ($this->alias_domains ?? []),
            $this->server?->connection_type,
            $this->deploy_path,
            $this->web_root,
            $this->ssl_state,
            (bool) $this->force_https,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function vhostPreviewFromSite(): array
    {
        return SiteVhostPreview::build($this);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hosts
     */
    protected function liveSslStateFromSnapshot(array $hosts): string
    {
        if ($hosts === []) {
            return 'unknown';
        }

        $states = collect($hosts)
            ->map(function (array $host): ?string {
                $installed = (bool) data_get($host, 'installed', false);
                $domain = filled(data_get($host, 'domain')) ? (string) data_get($host, 'domain') : null;

                if (! $installed && ! $domain) {
                    return null;
                }

                return $installed ? 'valid' : 'installed';
            })
            ->filter()
            ->values();

        if ($states->contains('valid')) {
            return 'ssl ready';
        }

        if ($states->isNotEmpty()) {
            return 'installed';
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    public function getLiveConfigurationPreviewAttribute(): array
    {
        $snapshot = (array) ($this->live_configuration_snapshot ?? []);
        $domains = (array) data_get($snapshot, 'domains', []);
        $dns = (array) data_get($snapshot, 'dns', []);
        $ssl = (array) data_get($snapshot, 'ssl', []);
        $live = (array) data_get($snapshot, 'live', []);

        return [
            'supported' => ($this->server?->connection_type ?? null) === 'cpanel',
            'message' => ($this->server?->connection_type ?? null) === 'cpanel'
                ? 'This tab shows the live cPanel inventory that was last synced for this site.'
                : 'This tab also stores VPS vhost inventory when the server supports SSH inspection.',
            'source' => data_get($snapshot, 'source', 'cPanel'),
            'synced_at' => $this->live_configuration_synced_at?->format('M d, Y H:i') ?? 'never synced',
            'last_error' => $this->live_configuration_last_error ?: 'No sync errors recorded.',
            'snapshot' => $snapshot,
            'live' => $live,
            'expected' => (array) data_get($snapshot, 'expected', []),
            'drift' => $this->live_configuration_drift,
            'counts' => [
                'main_domain' => filled(data_get($domains, 'main')) ? 1 : 0,
                'addon_domains' => count((array) data_get($domains, 'addon_domains', [])),
                'subdomains' => count((array) data_get($domains, 'subdomains', [])),
                'parked_domains' => count((array) data_get($domains, 'parked_domains', [])),
                'dns_records' => count((array) data_get($dns, 'records', [])),
                'ssl_hosts' => count((array) data_get($ssl, 'hosts', [])),
                'live_highlights' => count((array) data_get($live, 'highlights', [])),
            ],
            'notes' => (array) data_get($snapshot, 'notes', []),
            'apply' => [
                'last_run_at' => $this->vhost_apply_last_run_at?->format('M d, Y H:i') ?? 'never applied',
                'last_error' => $this->vhost_apply_last_error ?: 'No apply errors recorded.',
                'last_output' => $this->vhost_apply_last_output ?: '',
                'steps' => (array) ($this->vhost_apply_last_steps ?? []),
            ],
        ];
    }

    public function getDomainBadgeAttribute(): string
    {
        return match ($this->domain_status) {
            'ready' => 'domain ready',
            default => 'needs setup',
        };
    }

    public function getDomainSummaryAttribute(): string
    {
        if (blank($this->primary_domain)) {
            return 'No primary domain configured yet.';
        }

        $subdomainCount = count($this->subdomains ?? []);
        $aliasCount = count($this->alias_domains ?? []);
        $subdomainText = $subdomainCount === 0 ? 'no' : (string) $subdomainCount;
        $aliasText = $aliasCount === 0 ? 'no' : (string) $aliasCount;

        return sprintf(
            '1 primary domain, %s subdomain%s, and %s alias domain%s.',
            $subdomainText,
            $subdomainCount === 1 ? '' : 's',
            $aliasText,
            $aliasCount === 1 ? '' : 's',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomainPreviewAttribute(): array
    {
        return SiteDomainPreview::build(
            $this->primary_domain,
            (array) ($this->subdomains ?? []),
            (array) ($this->alias_domains ?? []),
            $this->server?->connection_type,
            $this->deploy_path,
            $this->web_root,
            $this->ssl_state,
            (bool) $this->force_https,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getDnsPreviewAttribute(): array
    {
        return SiteDnsPreview::build($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSslPreviewAttribute(): array
    {
        return [
            'supported' => ($this->server?->connection_type ?? null) === 'cpanel' && (bool) ($this->server?->can_manage_ssl ?? false),
            'message' => ($this->server?->connection_type ?? null) === 'cpanel'
                ? 'This preview shows the cPanel SSL state for the site primary domain.'
                : 'SSL automation is currently cPanel-first. Other providers can still preview the state here.',
            'primary_domain' => $this->primary_domain,
            'ssl_state' => $this->ssl_state,
            'ssl_summary' => $this->ssl_summary,
            'force_https' => (bool) $this->force_https,
            'force_https_summary' => $this->force_https_summary,
            'ssl_last_synced_at' => $this->ssl_last_synced_at?->format('M d, Y H:i') ?? 'never synced',
            'ssl_last_error' => $this->ssl_last_error ?: 'No SSL errors recorded.',
            'steps' => [
                'Generate a certificate for the primary domain.',
                'Store the resulting ssl state on the site record.',
                'Enable HTTPS redirects when force https is turned on.',
            ],
        ];
    }

    public function getGithubCredentialProfileLabelAttribute(): string
    {
        return $this->githubCredentialProfile?->name
            ?? app(AppSettings::class)->defaultGithubCredentialProfile()?->name
            ?? 'No GitHub profile';
    }

    public function getDnsCredentialProfileLabelAttribute(): string
    {
        return $this->dnsCredentialProfile?->name
            ?? app(AppSettings::class)->defaultDnsCredentialProfile()?->name
            ?? 'No DNS profile';
    }

    public function getWebhookCredentialProfileLabelAttribute(): string
    {
        return $this->webhookCredentialProfile?->name
            ?? app(AppSettings::class)->defaultWebhookCredentialProfile()?->name
            ?? 'No webhook profile';
    }

    /**
     * @return array<string, mixed>
     */
    public function getVhostPreviewAttribute(): array
    {
        return SiteVhostPreview::build($this);
    }

    public function getTerminalPromptAttribute(): string
    {
        $serverName = $this->server?->name ?: 'site';
        $deployPath = filled($this->deploy_path) ? $this->deploy_path : '~';

        return sprintf('%s@%s:%s$ ', $this->name ?: 'site', $serverName, $deployPath);
    }

    public function terminalCommandPrefix(): string
    {
        if (! filled($this->deploy_path)) {
            return '';
        }

        $path = $this->deploy_path;

        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf('cd /d "%s" && ', str_replace('"', '""', $path));
        }

        return sprintf('cd %s && ', escapeshellarg($path));
    }

    /**
     * @return array<int, array{label: string, command: string, description: string, source: string}>
     */
    public function terminalAutocompleteSuggestions(): array
    {
        $suggestions = [
            [
                'label' => 'pwd',
                'command' => 'pwd',
                'description' => 'Show the current working directory for this site.',
                'source' => 'Site terminal',
            ],
            [
                'label' => 'ls -la',
                'command' => 'ls -la',
                'description' => 'List files in the current site folder.',
                'source' => 'Site terminal',
            ],
            [
                'label' => 'php -v',
                'command' => 'php -v',
                'description' => 'Check the PHP version available to the site.',
                'source' => 'Site terminal',
            ],
            [
                'label' => 'git status',
                'command' => 'git status',
                'description' => 'Inspect the repository state when the site uses Git deployment.',
                'source' => 'Site terminal',
            ],
        ];

        if ($this->deploy_source === 'git') {
            $suggestions[] = [
                'label' => 'composer -V',
                'command' => 'composer -V',
                'description' => 'Confirm Composer is available for deployments and maintenance tasks.',
                'source' => 'Site terminal',
            ];
        }

        if ($this->deploy_source === 'local') {
            $suggestions[] = [
                'label' => 'du -sh .',
                'command' => 'du -sh .',
                'description' => 'Check the current site folder size.',
                'source' => 'Site terminal',
            ];
        }

        return $suggestions;
    }

    public function getLastSuccessfulDeployBadgeAttribute(): string
    {
        $deployment = $this->deployments()
            ->where('status', 'successful')
            ->whereNotNull('release_path')
            ->latest('id')
            ->first();

        if (! $deployment) {
            return 'Never successful';
        }

        $timestamp = $deployment->started_at?->format('M d, Y H:i') ?? "Deployment #{$deployment->id}";

        return trim(sprintf('%s • %s', $timestamp, $deployment->release_path ?? ''));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentAdminDeploymentsAttribute(): array
    {
        return $this->deployments()
            ->visibleInAdmin()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (Deployment $deployment): array {
                return [
                    'source' => $deployment->source,
                    'status' => $deployment->status,
                    'branch' => $deployment->branch,
                    'commit_hash' => $deployment->commit_hash,
                    'release_path' => $deployment->release_path,
                    'started_at' => $deployment->started_at,
                    'finished_at' => $deployment->finished_at,
                    'error_message' => $deployment->error_message,
                    'output' => $deployment->output,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getCpanelDeployChecklistAttribute(): array
    {
        if (($this->server?->connection_type ?? null) !== 'cpanel') {
            return ['This site is not assigned to a cPanel server.'];
        }

        $items = [
            filled($this->deploy_path) ? 'Deploy path configured.' : 'Deploy path missing.',
            filled($this->server?->effectiveCpanelApiToken()) ? 'cPanel API profile configured.' : 'cPanel API profile missing.',
        ];

        if ($this->deploy_source === 'git') {
            $items[] = filled($this->repository_url) ? 'Git repository configured.' : 'Git repository missing.';
        }

        if ($this->deploy_source === 'local') {
            $items[] = filled($this->local_source_path) ? 'Local source path configured.' : 'Local source path missing.';
        }

        return $items;
    }

    public function getCpanelDeployStatusAttribute(): string
    {
        if (($this->server?->connection_type ?? null) !== 'cpanel') {
            return 'not applicable';
        }

        return collect($this->cpanel_deploy_checklist)
            ->contains(fn (string $item): bool => str_contains(strtolower($item), 'missing'))
            ? 'needs setup'
            : 'ready';
    }

    public function getCpanelDeploySummaryAttribute(): string
    {
        return match ($this->cpanel_deploy_status) {
            'ready' => 'The cPanel site is ready for provisioning and deployment.',
            'needs setup' => 'One or more cPanel deployment prerequisites are missing.',
            default => 'This site is not using a cPanel server.',
        };
    }

    public function getCurrentReleaseBadgeAttribute(): string
    {
        return filled($this->current_release_path) ? 'active' : 'inactive';
    }

    /**
     * @return array<int, string>
     */
    public function previousReleaseOptions(int $limit = 10): array
    {
        return $this->deployments()
            ->where('status', 'successful')
            ->whereNotNull('release_path')
            ->when(filled($this->current_release_path), fn ($query) => $query->where('release_path', '!=', $this->current_release_path))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function (Deployment $deployment): array {
                $labelParts = [
                    $deployment->started_at?->format('M d, Y H:i') ?? "Deployment #{$deployment->id}",
                    $deployment->branch ? "branch: {$deployment->branch}" : null,
                    $deployment->release_path,
                ];

                return [
                    $deployment->id => implode(' • ', array_values(array_filter($labelParts))),
                ];
            })
            ->all();
    }

    public function getLatestReleaseCleanupAttribute(): ?ReleaseCleanupRun
    {
        return $this->releaseCleanupRuns()->first();
    }

    public function getGithubWebhookDriftAttribute(): bool
    {
        return in_array($this->github_webhook_status, ['needs-sync', 'failed'], true);
    }

    public function getBackupStatusAttribute(): string
    {
        $latestBackup = $this->backups()
            ->where('operation', 'backup')
            ->latest('started_at')
            ->first();

        if (! $latestBackup) {
            return 'not run';
        }

        return match ($latestBackup->status) {
            'successful' => 'healthy',
            'failed' => 'needs attention',
            'running' => 'running',
            default => 'unknown',
        };
    }

    public function getBackupStatusBadgeAttribute(): string
    {
        return match ($this->backup_status) {
            'healthy' => 'ready',
            'needs attention' => 'attention needed',
            'running' => 'running',
            default => 'not run',
        };
    }

    public function getLatestBackupSnapshotPathAttribute(): ?string
    {
        return $this->latestSuccessfulBackup?->snapshot_path;
    }

    public function getLatestBackupSummaryAttribute(): string
    {
        $backup = $this->latestSuccessfulBackup;

        if (! $backup) {
            return 'No backups have been created yet.';
        }

        return trim(sprintf(
            '%s • %s',
            $backup->started_at?->format('M d, Y H:i') ?? "Backup #{$backup->id}",
            $backup->snapshot_path ?? 'no snapshot path recorded',
        ));
    }

    public function getRecentAdminBackupsAttribute(): array
    {
        return $this->backups()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (SiteBackup $backup): array {
                return [
                    'operation' => $backup->operation,
                    'status' => $backup->status,
                    'snapshot_path' => $backup->snapshot_path,
                    'restored_release_path' => $backup->restored_release_path,
                    'started_at' => $backup->started_at,
                    'finished_at' => $backup->finished_at,
                    'error_message' => $backup->error_message,
                    'output' => $backup->output,
                ];
            })
            ->all();
    }

    public function backupOptions(int $limit = 10): array
    {
        return $this->backups()
            ->where('operation', 'backup')
            ->where('status', 'successful')
            ->whereNotNull('snapshot_path')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function (SiteBackup $backup): array {
                $labelParts = [
                    $backup->started_at?->format('M d, Y H:i') ?? "Backup #{$backup->id}",
                    $backup->snapshot_path,
                ];

                return [
                    $backup->id => implode(' • ', array_values(array_filter($labelParts))),
                ];
            })
            ->all();
    }

    public function getLatestSuccessfulBackupAttribute(): ?SiteBackup
    {
        return $this->backups()
            ->where('operation', 'backup')
            ->where('status', 'successful')
            ->whereNotNull('snapshot_path')
            ->latest('started_at')
            ->first();
    }
}
