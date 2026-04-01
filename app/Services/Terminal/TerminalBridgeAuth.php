<?php

namespace App\Services\Terminal;

use App\Models\ServerTerminalSession;

class TerminalBridgeAuth
{
    public function token(ServerTerminalSession $session): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                $session->id,
                $session->server_id,
                $session->user_id ?? 0,
                $session->started_at?->timestamp ?? 0,
                $session->created_at?->timestamp ?? 0,
            ]),
            (string) config('app.key'),
        );
    }

    public function validate(ServerTerminalSession $session, string $token): bool
    {
        return hash_equals($this->token($session), $token);
    }
}
