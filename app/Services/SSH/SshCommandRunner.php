<?php

namespace App\Services\SSH;

use App\Models\Server;
use App\Services\Security\SshKeyService;
use App\Services\Server\ServerPuTTYKeyExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SshCommandRunner
{
    protected int $timeout = 0;

    protected int $minimumTimeout = 20;

    public function execute(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        if ($this->shouldUsePasswordAuthentication($server)) {
            if ($this->shouldUsePuTTY()) {
                return $this->executeWithPuTTYPassword($server, $commands, $onOutput);
            }

            return $this->executeWithPassword($server, $commands, $onOutput);
        }

        return $this->executeWithOpenSshKey($server, $commands, $onOutput);
    }

    protected function executeWithPuTTYKey(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        $puttyKey = app(ServerPuTTYKeyExporter::class)->export($server)['putty_private_key'] ?? null;

        if (! filled($puttyKey)) {
            throw new RuntimeException('SSH key authentication was requested, but no private key is stored for this server.');
        }

        $temporaryKeyPath = $this->writeTemporaryPrivateKey((string) $puttyKey, '.ppk');
        $hostKey = $this->hostKeyFingerprint($server);
        $command = is_array($commands) ? implode(' && ', $commands) : $commands;
        $process = new Process([
            $this->puttyExecutable('plink.exe'),
            '-ssh',
            '-batch',
            '-P',
            (string) $this->sshPort($server),
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
            (string) $this->sshPort($server),
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

    protected function executeWithPassword(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        $ssh = Ssh::create(
            $this->loginUsername($server),
            $server->host,
            $server->port ?? 22,
            (string) $server->sudo_password,
        )
            ->disableStrictHostKeyChecking();

        if ($onOutput) {
            $ssh->onOutput($onOutput);
        }

        return $ssh->execute($commands);
    }

    protected function executeWithOpenSshKey(Server $server, array|string $commands, ?callable $onOutput = null): Process
    {
        $privateKey = $server->effectiveSshKey();

        if (! filled($privateKey)) {
            throw new RuntimeException('SSH key authentication was requested, but no private key is stored for this server.');
        }

        $deployKey = app(SshKeyService::class)->exportDeployPrivateKey(
            (string) $privateKey,
            (string) data_get($server->sshCredentialProfile?->settings, 'passphrase', ''),
        ) ?: (string) $privateKey;

        $keyPath = $this->writeTemporaryPrivateKey($deployKey);

        try {
            $command = is_array($commands) ? implode(' && ', $commands) : $commands;
            if (PHP_OS_FAMILY === 'Windows') {
                $this->securePrivateKeyPermissions($keyPath);

                $process = new Process([
                    'ssh',
                    '-i',
                    $keyPath,
                    '-p',
                    (string) $this->sshPort($server),
                    '-o',
                    'BatchMode=yes',
                    '-o',
                    'StrictHostKeyChecking=no',
                    '-o',
                    'UserKnownHostsFile=/dev/null',
                    sprintf('%s@%s', $this->loginUsername($server), $server->host),
                    $command,
                ]);

                $process->setTimeout($this->effectiveTimeout());
                $process->run(function (string $type, string $buffer) use ($onOutput): void {
                    if ($onOutput) {
                        $onOutput($type, $buffer);
                    }
                });

                return $process;
            }

            $this->securePrivateKeyPermissions($keyPath);

            $process = new Process([
                'ssh',
                '-i',
                $keyPath,
                '-p',
                (string) $this->sshPort($server),
                '-o',
                'BatchMode=yes',
                '-o',
                'StrictHostKeyChecking=no',
                '-o',
                'UserKnownHostsFile=/dev/null',
                sprintf('%s@%s', $this->loginUsername($server), $server->host),
                $command,
            ]);

            $process->setTimeout($this->effectiveTimeout());
            $process->run(function (string $type, string $buffer) use ($onOutput): void {
                if ($onOutput) {
                    $onOutput($type, $buffer);
                }
            });

            return $process;
        } finally {
            if (File::exists($keyPath)) {
                File::delete($keyPath);
            }
        }
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

    protected function securePrivateKeyPermissions(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $identity = trim((string) ProcessFacade::timeout(10)->run(['whoami'])->output());

            if ($identity !== '') {
                $process = ProcessFacade::timeout(15)->run([
                    'icacls',
                    $path,
                    '/inheritance:r',
                    '/grant:r',
                    $identity.':(F)',
                ]);

                if ($process->failed()) {
                    throw new RuntimeException(trim($process->errorOutput() ?: $process->output()) ?: 'Unable to secure the SSH private key file permissions.');
                }

                return;
            }
        }

        @chmod($path, 0600);
    }

    protected function effectiveTimeout(): int
    {
        if ($this->timeout <= 0) {
            return 300;
        }

        return max($this->timeout, $this->minimumTimeout);
    }

    protected function shouldUsePuTTY(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && File::exists('C:\\Program Files\\PuTTY\\plink.exe');
    }

    protected function shouldUsePasswordAuthentication(Server $server): bool
    {
        return $server->connection_type === 'password' || blank($server->effectiveSshKey());
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
            throw new RuntimeException(trim($scanOutput) ?: 'Unable to determine the SSH host key.');
        }

        if (! preg_match('/^(\S+)\s+(\S+)\s+(\S+)$/', trim($line), $matches)) {
            throw new RuntimeException('Unable to parse the SSH host key line.');
        }

        $rawKey = base64_decode($matches[3], true);

        if ($rawKey === false) {
            throw new RuntimeException('Unable to decode the SSH host key blob.');
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
                (string) $this->sshPort($server),
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

    protected function sshPort(Server $server): int
    {
        return $server->effectiveSshPort() ?: $server->port ?: 22;
    }
}
