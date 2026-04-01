<?php

namespace App\Services\Terminal;

use App\Models\ServerTerminalSession;

class TerminalBridgeUrl
{
    public function make(ServerTerminalSession $session): array
    {
        if (! (bool) config('terminal.bridge.enabled', true)) {
            return [
                'enabled' => false,
                'url' => null,
                'token' => null,
            ];
        }

        $host = (string) config('terminal.bridge.host', '127.0.0.1');
        $port = (int) config('terminal.bridge.port', 8787);
        $scheme = (string) config('terminal.bridge.scheme', 'ws');
        $token = app(TerminalBridgeAuth::class)->token($session);

        return [
            'enabled' => true,
            'url' => sprintf(
                '%s://%s:%s?server_id=%d&session_id=%d&token=%s',
                $scheme,
                $host,
                $port,
                $session->server_id,
                $session->id,
                urlencode($token),
            ),
            'token' => $token,
        ];
    }
}
