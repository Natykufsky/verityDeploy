<?php

namespace App\Services\Server\Connections;

interface ConnectionStrategy
{
    public function run(string $command): string;

    /**
     * @param  callable(string, string): void|null  $onOutput
     */
    public function streamRun(string $command, ?callable $onOutput = null): string;
}
