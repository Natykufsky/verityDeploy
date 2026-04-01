<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerConnectionTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'status',
        'command',
        'output',
        'error_message',
        'exit_code',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
            'exit_code' => 'integer',
        ];
    }

    public function setCommandAttribute(string $value): void
    {
        $this->attributes['command'] = strtolower($value);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
