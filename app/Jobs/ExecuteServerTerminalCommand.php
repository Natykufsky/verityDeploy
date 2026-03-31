<?php

namespace App\Jobs;

use App\Models\ServerTerminalRun;
use App\Services\Server\ServerConnector;
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

    public function handle(ServerConnector $connector): void
    {
        $run = ServerTerminalRun::query()->with('server')->findOrFail($this->terminalRunId);

        $server = $run->server;

        if (! $server) {
            throw new RuntimeException('The server terminal run is missing its server reference.');
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
            'output' => $run->output ?? '',
        ]);

        $buffer = (string) ($run->output ?? '');
        $strategy = $connector->strategy($server, 600);

        try {
            $result = $strategy->streamRun($run->command, function (string $type, string $chunk) use (&$buffer, $run): void {
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
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'output' => trim($buffer) !== '' ? trim($buffer) : null,
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }
    }
}
