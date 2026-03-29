<?php

namespace App\Services\Server\Connections;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class LocalStrategy implements ConnectionStrategy
{
    public function __construct(
        protected int $timeout = 0,
    ) {
    }

    public function run(string $command): string
    {
        $process = Process::path(base_path());

        if ($this->timeout > 0) {
            $process->timeout($this->timeout);
        }

        $result = $process->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Local command failed.');
        }

        return trim($result->output());
    }
}
