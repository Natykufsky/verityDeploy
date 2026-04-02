<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

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

    /**
     * Scope to filter domains accessible to a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->whereHas('server', fn ($q) => $q->where('team_id', $teamId));
    }
}
