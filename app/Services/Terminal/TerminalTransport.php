<?php

namespace App\Services\Terminal;

use App\Models\Server;
use App\Models\ServerTerminalSession;

interface TerminalTransport
{
    public function open(Server $server, ?int $userId = null, array $metadata = []): ServerTerminalSession;

    public function heartbeat(ServerTerminalSession $session): void;

    public function close(ServerTerminalSession $session, ?int $exitCode = null, ?string $message = null): void;

    public function execute(ServerTerminalSession $session, string $command, ?callable $onOutput = null): string;
}
