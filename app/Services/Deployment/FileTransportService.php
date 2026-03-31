<?php

namespace App\Services\Deployment;

use App\Models\Deployment;
use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class FileTransportService
{
    public function packageLocalSourceArchive(Deployment $deployment): string
    {
        $site = $deployment->site;
        $sourcePath = rtrim((string) $site->local_source_path, DIRECTORY_SEPARATOR);

        if ($sourcePath === '') {
            throw new RuntimeException('A local source path must be configured for local deployments.');
        }

        if (! File::isDirectory($sourcePath)) {
            throw new RuntimeException("The local source path [{$sourcePath}] does not exist.");
        }

        $archivePath = storage_path('app/deployment-archives/'.$deployment->id.'-'.Str::uuid().'.tar.gz');
        File::ensureDirectoryExists(dirname($archivePath));

        $this->createLocalSourceArchive($sourcePath, $archivePath);

        return $archivePath;
    }

    /**
     * @return array<int, array{label: string, command: string}>
     */
    public function transferCommands(Deployment $deployment): array
    {
        $site = $deployment->site;

        return match ($site->deploy_source) {
            'local' => $this->localTransferCommands($deployment),
            default => $this->gitTransferCommands($deployment),
        };
    }

    /**
     * @return array<int, array{label: string, command: string}>
     */
    protected function gitTransferCommands(Deployment $deployment): array
    {
        $site = $deployment->site;
        $releasePath = $deployment->release_path ?: $this->releasePath($deployment);
        $branch = $this->sanitizeBranch($deployment->branch ?? $site->default_branch ?? 'main');

        return [
            [
                'label' => 'Clone repository',
                'command' => sprintf(
                    'git clone --branch %1$s --single-branch --depth 1 %2$s %3$s',
                    escapeshellarg($branch),
                    escapeshellarg($site->repository_url),
                    escapeshellarg($releasePath),
                ),
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, command: string}>
     */
    protected function localTransferCommands(Deployment $deployment): array
    {
        $site = $deployment->site;
        $remoteArchivePath = $this->remoteArchivePath($deployment);

        if (! filled($deployment->archive_uploaded_at)) {
            $archivePath = $this->packageLocalSourceArchive($deployment);

            try {
                $this->uploadArchive($site->server, $archivePath, $remoteArchivePath);

                $deployment->forceFill([
                    'archive_uploaded_at' => now(),
                ])->saveQuietly();
            } finally {
                if (File::exists($archivePath)) {
                    File::delete($archivePath);
                }
            }
        }
        $releasePath = $deployment->release_path ?: $this->releasePath($deployment);

        return [
            [
                'label' => 'Extract uploaded archive',
                'command' => sprintf(
                    'mkdir -p %1$s && tar -xzf %2$s -C %1$s && rm -f %2$s',
                    escapeshellarg($releasePath),
                    escapeshellarg($remoteArchivePath),
                ),
            ],
        ];
    }

    protected function uploadArchive(Server $server, string $archivePath, string $remoteArchivePath): void
    {
        if ($server->connection_type === 'password') {
            if ($this->shouldUsePuTTY()) {
                if (! File::exists($this->puttyExecutable('pscp.exe'))) {
                    throw new RuntimeException('PuTTY pscp.exe was not found on this machine. Install PuTTY or use SSH key authentication.');
                }

                $destination = sprintf('%s@%s:%s', $server->ssh_user, $server->ip_address, $remoteArchivePath);
                $passwordProcess = Process::timeout(300)->run([
                    $this->puttyExecutable('pscp.exe'),
                    '-batch',
                    '-P',
                    (string) ($server->ssh_port ?: 22),
                    '-pw',
                    (string) $server->sudo_password,
                    '-hostkey',
                    $this->hostKeyFingerprint($server),
                    $archivePath,
                    $destination,
                ]);

                if ($passwordProcess->failed()) {
                    throw new RuntimeException(trim($passwordProcess->errorOutput() ?: $passwordProcess->output()) ?: 'Unable to upload the deployment archive.');
                }

                return;
            }

            $passwordCommand = [
                'sshpass',
                '-p',
                (string) $server->sudo_password,
                'scp',
                '-P',
                (string) ($server->ssh_port ?: 22),
                $archivePath,
                sprintf('%s@%s:%s', $server->ssh_user, $server->ip_address, $remoteArchivePath),
            ];

            $passwordProcess = Process::timeout(300)->run($passwordCommand);

            if ($passwordProcess->failed()) {
                throw new RuntimeException(trim($passwordProcess->errorOutput() ?: $passwordProcess->output()) ?: 'Unable to upload the deployment archive.');
            }

            return;
        }

        if ($this->shouldUsePuTTY()) {
            if (blank($server->ssh_key)) {
                throw new RuntimeException('The server does not have an SSH private key configured for file transfer.');
            }

            $keyPath = $this->writeTemporaryPrivateKey((string) $server->ssh_key, '.ppk');
            $destination = sprintf('%s@%s:%s', $server->ssh_user, $server->ip_address, $remoteArchivePath);

            try {
                $scp = Process::timeout(300)->run([
                    $this->puttyExecutable('pscp.exe'),
                    '-batch',
                    '-P',
                    (string) ($server->ssh_port ?: 22),
                    '-i',
                    $keyPath,
                    '-hostkey',
                    $this->hostKeyFingerprint($server),
                    $archivePath,
                    $destination,
                ]);

                if ($scp->failed()) {
                    throw new RuntimeException(trim($scp->errorOutput() ?: $scp->output()) ?: 'Unable to upload the deployment archive.');
                }

                return;
            } finally {
                if (File::exists($keyPath)) {
                    File::delete($keyPath);
                }
            }
        }

        $agentEnv = $this->startSshAgent();

        try {
            $keyLoad = Process::env($agentEnv)
                ->timeout(30)
                ->input(rtrim((string) $server->ssh_key).PHP_EOL)
                ->run(['ssh-add', '-']);

            if ($keyLoad->failed()) {
                throw new RuntimeException(trim($keyLoad->errorOutput() ?: $keyLoad->output()) ?: 'Unable to load the SSH key into the agent.');
            }

            $destination = sprintf('%s@%s:%s', $server->ssh_user, $server->ip_address, $remoteArchivePath);
            $scp = Process::env($agentEnv)
                ->timeout(300)
                ->run([
                    'scp',
                    '-P',
                    (string) ($server->ssh_port ?: 22),
                    '-o',
                    'StrictHostKeyChecking=no',
                    '-o',
                    'UserKnownHostsFile=/dev/null',
                    $archivePath,
                    $destination,
                ]);

            if ($scp->failed()) {
                throw new RuntimeException(trim($scp->errorOutput() ?: $scp->output()) ?: 'Unable to upload the deployment archive.');
            }
        } finally {
            $this->stopSshAgent($agentEnv);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function startSshAgent(): array
    {
        $process = Process::timeout(30)->run(['ssh-agent', '-s']);

        if ($process->failed()) {
            throw new RuntimeException(trim($process->errorOutput() ?: $process->output()) ?: 'Unable to start ssh-agent.');
        }

        $output = $process->output();
        preg_match('/SSH_AUTH_SOCK=([^;]+)/', $output, $socketMatches);
        preg_match('/SSH_AGENT_PID=([0-9]+)/', $output, $pidMatches);

        if (! isset($socketMatches[1], $pidMatches[1])) {
            throw new RuntimeException('Unable to parse ssh-agent environment.');
        }

        return [
            'SSH_AUTH_SOCK' => trim($socketMatches[1]),
            'SSH_AGENT_PID' => trim($pidMatches[1]),
        ];
    }

    /**
     * @param  array<string, string>  $agentEnv
     */
    protected function stopSshAgent(array $agentEnv): void
    {
        try {
            Process::env($agentEnv)->timeout(30)->run(['ssh-agent', '-k']);
        } catch (Throwable) {
            // Best-effort cleanup only.
        }
    }

    protected function remoteArchivePath(Deployment $deployment): string
    {
        return sprintf('/tmp/veritydeploy-%s.tar.gz', $deployment->id);
    }

    protected function shouldUsePuTTY(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && File::exists('C:\\Program Files\\PuTTY\\pscp.exe');
    }

    protected function puttyExecutable(string $name): string
    {
        return 'C:\\Program Files\\PuTTY\\'.$name;
    }

    protected function writeTemporaryPrivateKey(string $privateKey, string $extension = '.key'): string
    {
        $directory = storage_path('app/ssh-keys');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().$extension;
        File::put($path, $privateKey);

        return $path;
    }

    protected function hostKeyFingerprint(Server $server): string
    {
        $scan = Process::timeout(15)->run([
            'ssh-keyscan',
            '-p',
            (string) ($server->ssh_port ?: 22),
            '-t',
            'ed25519',
            $server->ip_address,
        ]);

        if ($scan->failed()) {
            throw new RuntimeException(trim($scan->errorOutput() ?: $scan->output()) ?: 'Unable to determine the SSH host key.');
        }

        $line = collect(explode(PHP_EOL, trim($scan->output())))
            ->first(fn (string $line): bool => trim($line) !== '' && ! str_starts_with($line, '#'));

        if (! is_string($line)) {
            throw new RuntimeException('Unable to parse the SSH host key scan output.');
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

    protected function releasePath(Deployment $deployment): string
    {
        $basePath = rtrim($deployment->site->deploy_path, '/');
        $stamp = now()->utc()->format('YmdHis');

        return sprintf('%s/releases/%s-%s', $basePath, $stamp, $deployment->id);
    }

    protected function sanitizeBranch(string $branch): string
    {
        return preg_replace('/[^A-Za-z0-9._\-\/]/', '', $branch) ?: 'main';
    }

    protected function shellPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    protected function createLocalSourceArchive(string $sourcePath, string $archivePath): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $package = Process::path($sourcePath)->timeout(300)->run([
                'tar',
                '--exclude=.git',
                '--exclude=vendor',
                '--exclude=node_modules',
                '--exclude=storage',
                '-czf',
                $archivePath,
                '.',
            ]);

            if ($package->failed()) {
                throw new RuntimeException(trim($package->errorOutput() ?: $package->output()) ?: 'Unable to package the local source directory.');
            }

            return;
        }

        $tarPath = preg_replace('/\.gz$/', '', $archivePath) ?: ($archivePath.'.tar');
        $tempTarPath = str_ends_with($tarPath, '.tar') ? $tarPath : $tarPath.'.tar';

        if (File::exists($tempTarPath)) {
            File::delete($tempTarPath);
        }

        if (File::exists($archivePath)) {
            File::delete($archivePath);
        }

        $phar = new PharData($tempTarPath);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($path, strlen($sourcePath))), '/');

            if ($relativePath === '' || $this->shouldExcludePath($relativePath)) {
                continue;
            }

            if ($fileInfo->isDir()) {
                $phar->addEmptyDir($relativePath);
                continue;
            }

            $phar->addFile($path, $relativePath);
        }

        if (File::exists($archivePath)) {
            File::delete($archivePath);
        }

        $phar->compress(Phar::GZ);

        $gzPath = $tempTarPath.'.gz';
        if (! File::move($gzPath, $archivePath)) {
            throw new RuntimeException('Unable to finalize the compressed archive.');
        }

        if (File::exists($tempTarPath)) {
            File::delete($tempTarPath);
        }
    }

    protected function shouldExcludePath(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        return str_starts_with($normalized, '.git')
            || str_starts_with($normalized, 'vendor/')
            || str_starts_with($normalized, 'node_modules/')
            || str_starts_with($normalized, 'storage/');
    }
}
