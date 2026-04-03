<?php

namespace App\Models;

use App\Services\Domains\DomainServerSyncService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Domain extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Domain $domain): void {
            $domain->syncLiveDomain('created');
        });

        static::updated(function (Domain $domain): void {
            $domain->syncLiveDomain('updated');
        });

        static::deleted(function (Domain $domain): void {
            $domain->syncLiveDomain('deleted');
        });
    }

    protected $fillable = [
        'server_id',
        'site_id',
        'name',
        'type',
        'php_version',
        'web_root',
        'is_ssl_enabled',
        'ssl_status',
        'ssl_expires_at',
        'ssl_certificate',
        'ssl_key',
        'ssl_chain',
        'external_id',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_ssl_enabled' => 'boolean',
            'is_active' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    protected function syncLiveDomain(string $action): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $result = match ($action) {
            'created' => app(DomainServerSyncService::class)->syncCreated($this),
            'updated' => app(DomainServerSyncService::class)->syncUpdated($this),
            'deleted' => app(DomainServerSyncService::class)->syncDeleted($this),
            default => ['success' => false, 'message' => 'Unknown domain sync action.'],
        };

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException((string) ($result['message'] ?? 'Unable to sync the domain to cPanel.'));
        }
    }

    /**
     * Scope to filter domains accessible to a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->whereHas('server', fn ($q) => $q->where('team_id', $teamId));
    }
}
