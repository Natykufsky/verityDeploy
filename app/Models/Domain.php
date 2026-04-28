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

    public function getSslBadgeAttribute(): string
    {
        return match ((string) ($this->ssl_status ?: 'unconfigured')) {
            'issued' => 'ssl issued',
            'pending' => 'ssl pending',
            'expired' => 'ssl expired',
            'failed' => 'ssl failed',
            default => 'ssl unconfigured',
        };
    }

    public function getSslSummaryAttribute(): string
    {
        return match ((string) ($this->ssl_status ?: 'unconfigured')) {
            'issued' => 'The certificate is tracked and should be serving HTTPS.',
            'pending' => 'The certificate is waiting on renewal or issuance.',
            'expired' => 'The certificate has expired and should be renewed.',
            'failed' => 'The last SSL attempt failed and needs attention.',
            default => 'No SSL certificate state has been tracked for this domain yet.',
        };
    }

    public function getSslRenewalStatusAttribute(): string
    {
        if (! (bool) $this->is_ssl_enabled) {
            return 'disabled';
        }

        if (blank($this->ssl_expires_at)) {
            return 'unknown';
        }

        $daysRemaining = now()->diffInDays($this->ssl_expires_at, false);

        if ($daysRemaining < 0) {
            return 'expired';
        }

        if ($daysRemaining <= 30) {
            return 'renewal due';
        }

        return 'healthy';
    }

    public function getSslRenewalBadgeAttribute(): string
    {
        return match ($this->ssl_renewal_status) {
            'healthy' => 'renewal healthy',
            'renewal due' => 'renewal due',
            'expired' => 'renewal overdue',
            'disabled' => 'tracking disabled',
            default => 'renewal unknown',
        };
    }

    public function getSslRenewalSummaryAttribute(): string
    {
        return match ($this->ssl_renewal_status) {
            'healthy' => 'The certificate is not due for renewal yet.',
            'renewal due' => 'The certificate is close to expiry and should be renewed soon.',
            'expired' => 'The certificate is already expired and needs immediate attention.',
            'disabled' => 'SSL tracking is disabled for this domain.',
            default => 'The certificate expiry date has not been tracked yet.',
        };
    }

    public function getSslMaterialSummaryAttribute(): string
    {
        return collect([
            'cert' => filled($this->ssl_certificate) ? 'certificate stored' : 'certificate missing',
            'key' => filled($this->ssl_key) ? 'private key stored' : 'private key missing',
            'chain' => filled($this->ssl_chain) ? 'chain stored' : 'chain missing',
        ])->values()->implode(', ');
    }

    public function getSslExpiresBadgeAttribute(): string
    {
        return filled($this->ssl_expires_at)
            ? $this->ssl_expires_at->format('M d, Y H:i')
            : 'not set';
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
