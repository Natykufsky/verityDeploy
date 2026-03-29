<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\ReleaseCleanupRun;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'repository_url',
        'default_branch',
        'deploy_path',
        'current_release_path',
        'local_source_path',
        'php_version',
        'web_root',
        'deploy_source',
        'webhook_secret',
        'environment_variables',
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
            'webhook_secret' => 'encrypted',
            'environment_variables' => 'array',
            'shared_files' => 'array',
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

    public function latestDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->latestOfMany();
    }

    public function getCurrentReleaseStatusAttribute(): string
    {
        return filled($this->current_release_path) ? 'active' : 'inactive';
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
}
