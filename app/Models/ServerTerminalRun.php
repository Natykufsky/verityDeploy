<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerTerminalRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'server_terminal_session_id',
        'user_id',
        'command',
        'output',
        'status',
        'exit_code',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ServerTerminalSession::class, 'server_terminal_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
