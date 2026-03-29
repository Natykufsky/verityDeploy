<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerHealthCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'status',
        'output',
        'error_message',
        'metrics',
        'tested_at',
        'exit_code',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'tested_at' => 'datetime',
            'exit_code' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
