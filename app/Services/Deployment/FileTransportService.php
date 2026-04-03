<?php

namespace App\Services\Deployment;

use App\Models\Deployment;
use App\Models\Server;
use App\Services\Security\SshKeyService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

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

        $archivePath = storage_path('app/deployment-archives/'.$deployment->id.'-'.Str::uuid().'.zip');
        File::ensureDirectoryExists(dirname($archivePath));

        $ignoreFiltered = (bool) ($site->ignore_local_source_ignored_files ?? true);

        $this->createLocalSourceArchive($sourcePath, $archivePath, $ignoreFiltered);

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
                    'mkdir -p %1$s && unzip -o %2$s -d %1$s && rm -f %2$s',
                    escapeshellarg($releasePath),
                    escapeshellarg($remoteArchivePath),
                ),
            ],
        ];
    }

    protected function uploadArchive(Server $server, string $archivePath, string $remoteArchivePath): void
    {
        $sshUser = $server->effectiveSshUser() ?: 'root';
        $sshPort = $server->effectiveSshPort() ?: 22;
        $sshKey = $server->effectiveSshKey();
        $sudoPassword = $server->effectiveSudoPassword();
        $destination = sprintf('%s@%s:%s', $sshUser, $server->ip_address, $remoteArchivePath);

        if ($server->connection_type === 'password') {
            if ($this->shouldUsePuTTY()) {
                if (! File::exists($this->puttyExecutable('pscp.exe'))) {
                    throw new RuntimeException('PuTTY pscp.exe was not found on this machine. Install PuTTY or use SSH key authentication.');
                }

                $passwordProcess = Process::timeout(300)->run([
                    $this->puttyExecutable('pscp.exe'),
                    '-batch',
                    '-P',
                    (string) $sshPort,
                    '-pw',
                    (string) $sudoPassword,
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
                (string) $sudoPassword,
                'scp',
                '-P',
                (string) $sshPort,
                $archivePath,
                $destination,
            ];

            $passwordProcess = Process::timeout(300)->run($passwordCommand);

            if ($passwordProcess->failed()) {
                throw new RuntimeException(trim($passwordProcess->errorOutput() ?: $passwordProcess->output()) ?: 'Unable to upload the deployment archive.');
            }

            return;
        }

        $sshKeyService = app(SshKeyService::class);
        $normalizedKey = $sshKeyService->exportDeployPrivateKey(
            (string) $sshKey,
            (string) data_get($server->sshCredentialProfile?->settings, 'passphrase', ''),
        );

        if (! $normalizedKey) {
            throw new RuntimeException('The server SSH private key is not in a supported format.');
        }

        $keyPath = $this->writeTemporaryPrivateKey($normalizedKey);

        try {
            $this->securePrivateKeyPermissions($keyPath);

            $scp = Process::timeout(300)->run([
                'scp',
                '-i',
                $keyPath,
                '-P',
                (string) $sshPort,
                '-o',
                'BatchMode=yes',
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
            if (File::exists($keyPath)) {
                File::delete($keyPath);
            }
        }
    }

    protected function remoteArchivePath(Deployment $deployment): string
    {
        return sprintf('/tmp/veritydeploy-%s.zip', $deployment->id);
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

    protected function securePrivateKeyPermissions(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $identity = trim((string) Process::timeout(10)->run(['whoami'])->output());

            if ($identity !== '') {
                $process = Process::timeout(15)->run([
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

    protected function hostKeyFingerprint(Server $server): string
    {
        $scan = Process::timeout(15)->run([
            'ssh-keyscan',
            '-p',
            (string) ($server->effectiveSshPort() ?: 22),
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

    protected function createLocalSourceArchive(string $sourcePath, string $archivePath, bool $ignoreFiltered): void
    {
        if (File::exists($archivePath)) {
            File::delete($archivePath);
        }

        $paths = $this->packageSourcePaths($sourcePath, $ignoreFiltered);

        if ($paths === []) {
            throw new RuntimeException('No files were found to package from the local source directory.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is not available on this machine.');
        }

        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException('Unable to create the deployment zip archive.');
        }

        foreach ($paths as $relativePath) {
            $fullPath = $sourcePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (! File::exists($fullPath) || ! is_file($fullPath)) {
                continue;
            }

            $zip->addFile($fullPath, $relativePath);
        }

        $zip->close();
    }

    /**
     * @return array<int, string>
     */
    protected function packageSourcePaths(string $sourcePath, bool $ignoreFiltered): array
    {
        if ($ignoreFiltered && $this->hasGitRepository($sourcePath)) {
            $gitPaths = $this->gitTrackedAndUnignoredFiles($sourcePath);

            if ($gitPaths !== []) {
                return $gitPaths;
            }
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($path, strlen($sourcePath))), '/');

            if ($relativePath === '' || ($ignoreFiltered && $this->shouldExcludePath($relativePath))) {
                continue;
            }

            $paths[] = $relativePath;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<int, string>
     */
    protected function gitTrackedAndUnignoredFiles(string $sourcePath): array
    {
        $process = Process::path($sourcePath)->timeout(60)->run([
            'git',
            'ls-files',
            '-co',
            '--exclude-standard',
        ]);

        if ($process->failed()) {
            return [];
        }

        return collect(preg_split('/\R/', trim($process->output())) ?: [])
            ->map(fn (string $line): string => trim(str_replace('\\', '/', $line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    protected function hasGitRepository(string $sourcePath): bool
    {
        return File::isDirectory($sourcePath.'/.git');
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
