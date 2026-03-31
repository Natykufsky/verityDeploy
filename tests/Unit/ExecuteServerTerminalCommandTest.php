<?php

namespace Tests\Unit;

use App\Jobs\ExecuteServerTerminalCommand;
use App\Models\Server;
use App\Models\ServerTerminalRun;
use App\Services\Server\Connections\ConnectionStrategy;
use App\Services\Server\ServerConnector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExecuteServerTerminalCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_streams_output_into_the_terminal_run(): void
    {
        $server = Server::query()->create([
            'name' => 'Streaming Server',
            'ip_address' => '203.0.113.91',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $run = ServerTerminalRun::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'command' => 'uptime',
            'status' => 'queued',
            'started_at' => now(),
        ]);

        $strategy = new class () implements ConnectionStrategy {
            public function run(string $command): string
            {
                return $this->streamRun($command);
            }

            public function streamRun(string $command, ?callable $onOutput = null): string
            {
                if ($onOutput) {
                    $onOutput('output', "line one\n");
                    $onOutput('output', "line two\n");
                }

                return "line one\nline two\n";
            }
        };

        app()->instance(ServerConnector::class, new class ($strategy) extends ServerConnector {
            public function __construct(protected ConnectionStrategy $strategy)
            {
            }

            public function strategy(Server $server, int $timeout = 0): ConnectionStrategy
            {
                return $this->strategy;
            }
        });

        app(ExecuteServerTerminalCommand::class, ['terminalRunId' => $run->id])->handle(app(ServerConnector::class));

        $run = $run->fresh();

        $this->assertSame('successful', $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertStringContainsString('line one', (string) $run->output);
        $this->assertStringContainsString('line two', (string) $run->output);
        $this->assertNotNull($run->finished_at);
    }
}
