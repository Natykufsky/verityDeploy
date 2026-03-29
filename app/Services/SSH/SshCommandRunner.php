<?php

namespace App\Services\SSH;

use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Illuminate\Support\Str;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SshCommandRunner
{
    public function execute(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        if ($this->shouldUsePuTTY()) {
            return $this->executeWithPuTTY($server, $commands, $onOutput);
        }

        $ssh = Ssh::create($server->username, $server->host, $server->port ?? 22)
            ->disableStrictHostKeyChecking();

        $temporaryKeyPath = null;

        if ($server->private_key) {
            $temporaryKeyPath = $this->writeTemporaryPrivateKey($server->private_key);
            $ssh->usePrivateKey($temporaryKeyPath);
        }

        if ($onOutput) {
            $ssh->onOutput($onOutput);
        }

        try {
            return $ssh->execute($commands);
        } finally {
            if ($temporaryKeyPath && File::exists($temporaryKeyPath)) {
                File::delete($temporaryKeyPath);
            }
        }
    }

    protected function executeWithPuTTY(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        $temporaryKeyPath = $this->writeTemporaryPrivateKey((string) $server->private_key, '.ppk');
        $hostKey = $this->hostKeyFingerprint($server);
        $command = is_array($commands) ? implode(' && ', $commands) : $commands;
        $process = new Process([
            $this->puttyExecutable('plink.exe'),
            '-ssh',
            '-batch',
            '-P',
            (string) ($server->port ?: 22),
            '-i',
            $temporaryKeyPath,
            '-hostkey',
            $hostKey,
            sprintf('%s@%s', $server->username, $server->host),
            $command,
        ]);

        $process->setTimeout(300);

        try {
            $process->run(function (string $type, string $buffer) use ($onOutput): void {
                if ($onOutput) {
                    $onOutput($type, $buffer);
                }
            });
        } finally {
            if (File::exists($temporaryKeyPath)) {
                File::delete($temporaryKeyPath);
            }
        }

        return $process;
    }

    protected function writeTemporaryPrivateKey(string $privateKey): string
    {
        $directory = storage_path('app/ssh-keys');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().'.key';
        File::put($path, $privateKey);

        return $path;
    }

    protected function shouldUsePuTTY(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && File::exists('C:\\Program Files\\PuTTY\\plink.exe');
    }

    protected function puttyExecutable(string $name): string
    {
        return 'C:\\Program Files\\PuTTY\\'.$name;
    }

    protected function hostKeyFingerprint(Server $server): string
    {
        $scan = ProcessFacade::timeout(15)->run([
            'ssh-keyscan',
            '-p',
            (string) ($server->port ?: 22),
            '-t',
            'ed25519',
            $server->host,
        ]);

        if ($scan->failed()) {
            throw new \RuntimeException(trim($scan->errorOutput() ?: $scan->output()) ?: 'Unable to determine the SSH host key.');
        }

        $line = collect(explode(PHP_EOL, trim($scan->output())))
            ->first(fn (string $line): bool => trim($line) !== '' && ! str_starts_with($line, '#'));

        if (! is_string($line)) {
            throw new \RuntimeException('Unable to parse the SSH host key scan output.');
        }

        if (! preg_match('/^(\S+)\s+(\S+)\s+(\S+)$/', trim($line), $matches)) {
            throw new \RuntimeException('Unable to parse the SSH host key line.');
        }

        $rawKey = base64_decode($matches[3], true);

        if ($rawKey === false) {
            throw new \RuntimeException('Unable to decode the SSH host key blob.');
        }

        $fingerprint = rtrim(base64_encode(hash('sha256', $rawKey, true)), '=');

        return sprintf('%s 255 SHA256:%s', $matches[1], $fingerprint);
    }
}
