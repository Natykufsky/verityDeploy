<?php

namespace App\Services\Terminal;

use App\Models\Server;
use App\Models\ServerTerminalSession;
use App\Services\Server\ServerConnector;

class SshTerminalTransport implements TerminalTransport
{
    public function __construct(
        protected TerminalSessionManager $sessions,
        protected ServerConnector $connector,
    ) {}

    public function open(Server $server, ?int $userId = null, array $metadata = []): ServerTerminalSession
    {
        return $this->sessions->openForServer($server, $userId, array_merge([
            'bridge' => 'command-stream',
            'transport' => 'ssh',
        ], $metadata));
    }

    public function heartbeat(ServerTerminalSession $session): void
    {
        $this->sessions->touch($session);
    }

    public function close(ServerTerminalSession $session, ?int $exitCode = null, ?string $message = null): void
    {
        $this->sessions->close($session, $exitCode, $message);
    }

    public function execute(ServerTerminalSession $session, string $command, ?callable $onOutput = null): string
    {
        $server = $session->server()->firstOrFail();
        $this->heartbeat($session);

        $strategy = $this->connector->strategy($server, 600);
        $result = $strategy->streamRun($command, function (string $type, string $chunk) use ($onOutput, $session): void {
            if ($onOutput) {
                $onOutput($type, $chunk);
            }

            $this->heartbeat($session);
        });

        $this->heartbeat($session);

        return (string) $result;
    }
}
