<?php

namespace App\Services\Server;

use App\Models\Server;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ServerKeyGenerator
{
    /**
     * @return array{private_key: string, public_key: string}
     */
    public function generate(Server $server): array
    {
        $privateProcess = Process::run(['openssl', 'genpkey', '-algorithm', 'ED25519']);

        if ($privateProcess->failed()) {
            throw new RuntimeException(trim($privateProcess->errorOutput() ?: $privateProcess->output()) ?: 'Unable to generate SSH key pair.');
        }

        $privateKey = trim($privateProcess->output());

        $publicProcess = Process::input($privateKey)->run(['openssl', 'pkey', '-pubout']);

        if ($publicProcess->failed()) {
            throw new RuntimeException(trim($publicProcess->errorOutput() ?: $publicProcess->output()) ?: 'Unable to generate SSH public key.');
        }

        $publicKey = trim($publicProcess->output());

        $server->update([
            'connection_type' => 'ssh_key',
            'ssh_key' => $privateKey,
        ]);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }
}
