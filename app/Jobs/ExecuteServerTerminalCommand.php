<?php

namespace App\Jobs;

use App\Models\ServerTerminalRun;
use App\Models\ServerTerminalSession;
use App\Services\Terminal\TerminalTransport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ExecuteServerTerminalCommand implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $terminalRunId)
    {
    }

    public function handle(TerminalTransport $transport): void
    {
        $run = ServerTerminalRun::query()->with(['server', 'session'])->findOrFail($this->terminalRunId);

        $server = $run->server;

        if (! $server) {
            throw new RuntimeException('The server terminal run is missing its server reference.');
        }

        $session = $run->session;

        if ($session instanceof ServerTerminalSession) {
            $transport->heartbeat($session);
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
            'output' => $run->output ?? '',
        ]);

        $buffer = (string) ($run->output ?? '');
        $session ??= $transport->open($server, $run->user_id, ['ui' => 'server-terminal']);

        try {
            $result = $transport->execute($session, $run->command, function (string $type, string $chunk) use (&$buffer, $run): void {
                $buffer .= $chunk;

                $run->update([
                    'output' => $buffer,
                    'status' => 'running',
                ]);
            });

            $run->update([
                'status' => 'successful',
                'output' => trim($result ?: $buffer),
                'exit_code' => 0,
                'finished_at' => now(),
                'error_message' => null,
            ]);

            if ($session) {
                $transport->heartbeat($session);
            }
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'output' => trim($buffer) !== '' ? trim($buffer) : null,
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            if ($session) {
                $transport->close($session, $run->exit_code, $throwable->getMessage());
            }

            throw $throwable;
        }
    }
}
