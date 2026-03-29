<?php

namespace App\Services\Server\Connections;

interface ConnectionStrategy
{
    public function run(string $command): string;
}
