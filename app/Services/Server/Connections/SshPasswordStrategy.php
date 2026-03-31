<?php

namespace App\Services\Server\Connections;

use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Spatie\Ssh\Ssh;

class SshPasswordStrategy implements ConnectionStrategy
{
    protected int $minimumTimeout = 20;

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
        if ($this->shouldUsePuTTY()) {
            return $this->streamRunWithPuTTY($command, $onOutput);
        }

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

    protected function streamRunWithPuTTY(string $command, ?callable $onOutput = null): string
    {
        if (! File::exists('C:\\Program Files\\PuTTY\\plink.exe')) {
            throw new RuntimeException('PuTTY plink.exe was not found on this machine. Install PuTTY or use SSH key authentication.');
        }

        try {
            $hostKey = $this->hostKeyFingerprint();
        } catch (RuntimeException $exception) {
            throw new RuntimeException($this->hintForHostKeyFailure($exception->getMessage()));
        }

        $result = Process::timeout($this->effectiveTimeout())
            ->run([
                $this->puttyExecutable('plink.exe'),
                '-batch',
                '-ssh',
                '-P',
                (string) ($this->server->ssh_port ?: 22),
                '-pw',
                (string) $this->server->sudo_password,
                '-hostkey',
                $hostKey,
                sprintf('%s@%s', $this->server->ssh_user, $this->server->ip_address),
                $command,
            ], function (string $type, string $output) use ($onOutput): void {
                if ($onOutput) {
                    $onOutput($type, $output);
                }
            });

        if ($result->failed()) {
            throw new RuntimeException($this->hintForLoginFailure(trim($result->errorOutput() ?: $result->output()) ?: 'SSH command failed.'));
        }

        return trim($result->output());
    }

    protected function ssh(): Ssh
    {
        $ssh = Ssh::create(
            $this->server->ssh_user,
            $this->server->ip_address,
            $this->server->ssh_port ?: 22,
            $this->server->sudo_password,
        )->disableStrictHostKeyChecking();

        if ($this->timeout > 0) {
            $ssh->setTimeout($this->effectiveTimeout());
        }

        return $ssh;
    }

    protected function shouldUsePuTTY(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    protected function puttyExecutable(string $name): string
    {
        return 'C:\\Program Files\\PuTTY\\'.$name;
    }

    protected function hostKeyFingerprint(): string
    {
        $scan = $this->runHostKeyScan();

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

    protected function runHostKeyScan()
    {
        $scan = Process::timeout(15)->run([
            PHP_OS_FAMILY === 'Windows' ? $this->windowsOpenSshExecutable('ssh-keyscan.exe') : 'ssh-keyscan',
            '-p',
            (string) ($this->server->ssh_port ?: 22),
            '-t',
            'ed25519',
            $this->server->ip_address,
        ]);

        if (trim($scan->output().PHP_EOL.$scan->errorOutput()) !== '') {
            return $scan;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return Process::timeout(15)->run([
                $this->windowsOpenSshExecutable('ssh-keyscan.exe'),
                '-p',
                (string) ($this->server->ssh_port ?: 22),
                '-t',
                'ed25519',
                $this->server->ip_address,
            ]);
        }

        return $scan;
    }

    protected function windowsOpenSshExecutable(string $name): string
    {
        return 'C:\\Windows\\System32\\OpenSSH\\'.$name;
    }

    protected function hintForHostKeyFailure(string $message): string
    {
        $message = trim($message);

        return match (true) {
            str_contains($message, 'ssh-keyscan') => 'Unable to inspect the SSH host key because ssh-keyscan is missing or unavailable. Install the OpenSSH client tools, or switch this server to SSH key authentication.',
            str_contains($message, 'Unable to determine the SSH host key') => sprintf(
                'Unable to determine the SSH host key for %s:%s. This usually means the SSH port is wrong, SSH is disabled for the account, or the host blocks host-key scanning. If this is a cPanel host, rediscover the SSH port first and make sure the account has SSH access enabled; port 22 is often not the correct account SSH port.',
                $this->server->ip_address,
                $this->server->ssh_port ?: 22,
            ),
            str_contains($message, 'Unable to parse the SSH host key') => 'The host responded to key scanning, but the output could not be parsed. The server may be returning an unexpected key type or a non-SSH response.',
            default => $message !== '' ? $message : sprintf(
                'Unable to determine the SSH host key for %s:%s. Check the SSH port, confirm the host allows SSH key scanning, and verify that password SSH login is enabled for the account. On cPanel hosts, run Discover first so the account SSH port is set correctly.',
                $this->server->ip_address,
                $this->server->ssh_port ?: 22,
            ),
        };
    }

    protected function hintForLoginFailure(string $message): string
    {
        $message = trim($message);

        return match (true) {
            str_contains(strtolower($message), 'permission denied') => 'PuTTY reached the server, but the username/password was rejected. Confirm the SSH username, SSH password, and that password SSH login is allowed for this account.',
            str_contains(strtolower($message), 'timeout') => 'PuTTY could not complete the login handshake. Check the SSH port, firewall, and whether the host allows password login on that port.',
            str_contains(strtolower($message), 'host key') => 'PuTTY could not trust the server host key. Rediscover the SSH port and confirm the host is reachable before trying again.',
            default => $message !== '' ? $message : 'SSH command failed. Check the SSH port, username, password, and host policy.',
        };
    }

    protected function effectiveTimeout(): int
    {
        if ($this->timeout <= 0) {
            return 300;
        }

        return max($this->timeout, $this->minimumTimeout);
    }
}
