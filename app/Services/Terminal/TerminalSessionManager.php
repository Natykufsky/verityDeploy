<?php

namespace App\Services\Terminal;

use App\Models\Server;
use App\Models\ServerTerminalSession;
use Illuminate\Support\Arr;

class TerminalSessionManager
{
    public function openForServer(Server $server, ?int $userId = null, array $metadata = []): ServerTerminalSession
    {
        $session = $this->latestOpenForServer($server);

        if ($session) {
            $this->touch($session);

            return $session;
        }

        return $server->terminalSessions()->create([
            'user_id' => $userId,
            'status' => 'open',
            'shell' => 'bash',
            'host' => $server->host,
            'port' => $server->port ?: $server->ssh_port,
            'username' => $server->username ?: $server->ssh_user,
            'prompt' => $server->terminal_prompt,
            'metadata' => array_filter(array_merge([
                'connection_type' => $server->connection_type,
                'provider_type' => $server->provider_type,
                'server_id' => $server->id,
            ], Arr::wrap($metadata))),
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function latestOpenForServer(Server $server): ?ServerTerminalSession
    {
        return $server->terminalSessions()
            ->where('status', 'open')
            ->latest('started_at')
            ->first();
    }

    public function touch(ServerTerminalSession $session): void
    {
        $session->forceFill([
            'last_activity_at' => now(),
        ])->saveQuietly();
    }

    public function close(ServerTerminalSession $session, ?int $exitCode = null, ?string $message = null): void
    {
        $session->forceFill([
            'status' => 'closed',
            'exit_code' => $exitCode,
            'error_message' => $message,
            'finished_at' => now(),
        ])->saveQuietly();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(?ServerTerminalSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'status' => $session->status,
            'shell' => $session->shell,
            'started_at' => $session->started_at?->toIso8601String(),
            'last_activity_at' => $session->last_activity_at?->toIso8601String(),
            'finished_at' => $session->finished_at?->toIso8601String(),
            'prompt' => $session->prompt,
            'metadata' => $session->metadata ?? [],
        ];
    }
}
