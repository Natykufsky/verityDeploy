<?php

namespace App\Services\Server\Connections;

use App\Models\Server;
use RuntimeException;
use Spatie\Ssh\Ssh;

class SshKeyStrategy implements ConnectionStrategy
{
    public function __construct(
        protected Server $server,
        protected int $timeout = 0,
    ) {
    }

    public function run(string $command): string
    {
        return $this->streamRun($command);
    }

    public function streamRun(string $command, ?callable $onOutput = null): string
    {
        $ssh = $this->ssh();

        if ($onOutput) {
            $ssh->onOutput($onOutput);
        }

        $process = $ssh->execute($command);

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'SSH command failed.');
        }

        return trim($process->getOutput());
    }

    protected function ssh(): Ssh
    {
        $ssh = Ssh::create($this->server->ssh_user, $this->server->ip_address, $this->server->ssh_port ?: 22)
            ->disableStrictHostKeyChecking();

        if ($this->timeout > 0) {
            $ssh->setTimeout($this->timeout);
        }

        if (filled($this->server->ssh_key)) {
            $ssh->usePrivateKey($this->server->ssh_key);
        }

        return $ssh;
    }
}
