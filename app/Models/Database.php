<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    protected $fillable = [
        'site_id',
        'server_id',
        'name',
        'username',
        'status',
        'provisioned_at',
        'last_synced_at',
        'last_error',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error' => 'string',
            'notes' => 'string',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
