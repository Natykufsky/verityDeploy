<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use App\Models\ReleaseCleanupRun;
use App\Models\SiteTerminalRun;
use App\Support\SiteDnsPreview;
use App\Support\SiteDomainPreview;
use App\Support\SiteVhostPreview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'php_version',
        'web_root',
        'deploy_source',
        'webhook_secret',
        'environment_variables',
        'shared_env_contents',
        'shared_files',
        'github_webhook_id',
        'github_webhook_status',
        'github_webhook_synced_at',
        'github_webhook_last_error',
        'active',
        'last_deployed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'webhook_secret' => EncryptedTextOrPlain::class,
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

    public function getDnsBadgeAttribute(): string
    {
        return $this->server?->dns_provider_badge ?? 'manual';
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
            filled($this->server?->cpanel_api_token) ? 'cPanel API token configured.' : 'cPanel API token missing.',
            filled($this->server?->cpanel_api_port) ? 'cPanel API port configured.' : 'cPanel API port missing.',
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
