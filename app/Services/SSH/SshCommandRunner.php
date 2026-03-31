<?php

namespace App\Services\SSH;

use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SshCommandRunner
{
    public function execute(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        if ($this->shouldUsePuTTY()) {
            return $this->shouldUsePasswordAuthentication($server)
                ? $this->executeWithPuTTYPassword($server, $commands, $onOutput)
                : $this->executeWithPuTTYKey($server, $commands, $onOutput);
        }

        $ssh = Ssh::create(
            $this->loginUsername($server),
            $server->host,
            $server->port ?? 22,
            $this->shouldUsePasswordAuthentication($server) ? (string) $server->sudo_password : null,
        )
            ->disableStrictHostKeyChecking();

        $temporaryKeyPath = null;

        if ($server->private_key && ! $this->shouldUsePasswordAuthentication($server)) {
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

    protected function executeWithPuTTYKey(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        if (! filled($server->private_key)) {
            throw new RuntimeException('SSH key authentication was requested, but no private key is stored for this server.');
        }

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

    protected function executeWithPuTTYPassword(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        if (! filled($server->sudo_password)) {
            throw new RuntimeException('Password authentication was requested, but no SSH password is stored for this server.');
        }

        $hostKey = $this->hostKeyFingerprint($server);
        $command = is_array($commands) ? implode(' && ', $commands) : $commands;
        $process = new Process([
            $this->puttyExecutable('plink.exe'),
            '-batch',
            '-ssh',
            '-P',
            (string) ($server->port ?: 22),
            '-pw',
            (string) $server->sudo_password,
            '-hostkey',
            $hostKey,
            sprintf('%s@%s', $this->loginUsername($server), $server->host),
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
            //
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

    protected function shouldUsePasswordAuthentication(Server $server): bool
    {
        return $server->connection_type === 'password' || blank($server->private_key);
    }

    protected function loginUsername(Server $server): string
    {
        return (string) ($server->username ?: $server->ssh_user ?: 'root');
    }

    protected function puttyExecutable(string $name): string
    {
        return 'C:\\Program Files\\PuTTY\\'.$name;
    }

    protected function hostKeyFingerprint(Server $server): string
    {
        $scan = $this->runHostKeyScan($server);

        $scanOutput = trim($scan->output().PHP_EOL.$scan->errorOutput());
        $line = $this->parseHostKeyScanOutput($scanOutput);

        if (! is_string($line)) {
            throw new \RuntimeException(trim($scanOutput) ?: 'Unable to determine the SSH host key.');
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

    protected function parseHostKeyScanOutput(string $output): ?string
    {
        $output = preg_replace('/^\xEF\xBB\xBF/', '', $output) ?? $output;

        if (preg_match('/^(\S+)\s+ssh-ed25519\s+([A-Za-z0-9+\/=]+)$/m', $output, $matches)) {
            return sprintf('%s ssh-ed25519 %s', $matches[1], $matches[2]);
        }

        return collect(preg_split('/\R/', $output) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'));
    }

    protected function runHostKeyScan(Server $server)
    {
        $command = [
            PHP_OS_FAMILY === 'Windows' ? $this->windowsOpenSshExecutable('ssh-keyscan.exe') : 'ssh-keyscan',
            '-p',
            (string) ($server->port ?: 22),
            '-t',
            'ed25519',
            $server->host,
        ];

        $scan = ProcessFacade::timeout(15)->run($command);

        if (trim($scan->output().PHP_EOL.$scan->errorOutput()) !== '') {
            return $scan;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return ProcessFacade::timeout(15)->run([
                $this->windowsOpenSshExecutable('ssh-keyscan.exe'),
                '-p',
                (string) ($server->port ?: 22),
                '-t',
                'ed25519',
                $server->host,
            ]);
        }

        return $scan;
    }

    protected function windowsOpenSshExecutable(string $name): string
    {
        return 'C:\\Windows\\System32\\OpenSSH\\'.$name;
    }
}
