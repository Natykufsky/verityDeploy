<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerTerminalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'user_id',
        'status',
        'shell',
        'host',
        'port',
        'username',
        'prompt',
        'metadata',
        'started_at',
        'last_activity_at',
        'finished_at',
        'exit_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'finished_at' => 'datetime',
            'port' => 'integer',
            'exit_code' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ServerTerminalRun::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function touchActivity(): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
        ])->saveQuietly();
    }

    public function close(?int $exitCode = null, ?string $message = null): void
    {
        $this->forceFill([
            'status' => 'closed',
            'exit_code' => $exitCode,
            'error_message' => $message,
            'finished_at' => now(),
        ])->saveQuietly();
    }
}
