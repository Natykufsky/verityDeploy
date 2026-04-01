<?php

namespace App\Services\Backups;

use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\User;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelSiteProvisioner;
use App\Services\Deployment\ReleaseManager;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SiteBackupService
{
    public function __construct(
        protected CpanelApiClient $cpanelApiClient,
        protected CpanelSiteProvisioner $cpanelSiteProvisioner,
        protected ReleaseManager $releaseManager,
        protected SshCommandRunner $sshCommandRunner,
    ) {}

    public function backup(Site $site, ?User $user = null, ?string $label = null): SiteBackup
    {
        $site->loadMissing('server');
        $sourceReleasePath = $this->currentReleasePath($site);

        if (blank($sourceReleasePath)) {
            throw new RuntimeException('A current release must be set before you can create a backup.');
        }

        $backup = SiteBackup::query()->create([
            'site_id' => $site->id,
            'triggered_by_user_id' => $user?->id,
            'operation' => 'backup',
            'status' => 'running',
            'label' => $label,
            'source_release_path' => $sourceReleasePath,
            'started_at' => now(),
        ]);

        $snapshotPath = $this->backupSnapshotPath($site, $backup);

        try {
            $backup->update([
                'snapshot_path' => $snapshotPath,
            ]);

            $output = $this->copyPath($site, $sourceReleasePath, $snapshotPath);
            $backup->update([
                'status' => 'successful',
                'output' => trim($output) !== '' ? $output : sprintf('Snapshot saved to %s.', $snapshotPath),
                'size_bytes' => $this->snapshotSize($site, $snapshotPath),
                'checksum' => $this->snapshotChecksum($site, $snapshotPath),
                'finished_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $backup->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }

        return $backup->fresh(['site.server', 'triggeredBy']);
    }

    public function restore(SiteBackup $backup, ?User $user = null): SiteBackup
    {
        $backup->loadMissing('site.server');
        $site = $backup->site;

        if ($backup->operation !== 'backup') {
            throw new RuntimeException('Only backup snapshots can be restored.');
        }

        if (blank($backup->snapshot_path)) {
            throw new RuntimeException('The selected backup does not have a snapshot path.');
        }

        $restore = SiteBackup::query()->create([
            'site_id' => $site->id,
            'triggered_by_user_id' => $user?->id,
            'source_backup_id' => $backup->id,
            'operation' => 'restore',
            'status' => 'running',
            'label' => $backup->label,
            'source_release_path' => $backup->snapshot_path,
            'started_at' => now(),
        ]);

        $restoreReleasePath = $this->restoreReleasePath($site, $restore);

        try {
            $restore->update([
                'restored_release_path' => $restoreReleasePath,
            ]);

            $output = $this->copyPath($site, (string) $backup->snapshot_path, $restoreReleasePath);
            $runtimeOutput = $this->syncAndActivate($site, $restoreReleasePath);

            $site->update([
                'last_deployed_at' => now(),
                'current_release_path' => $restoreReleasePath,
            ]);

            $restore->update([
                'status' => 'successful',
                'output' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    'Backup restored successfully.',
                    $output,
                    $runtimeOutput,
                ]))),
                'size_bytes' => $this->snapshotSize($site, $restoreReleasePath),
                'checksum' => $this->snapshotChecksum($site, $restoreReleasePath),
                'finished_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $restore->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }

        return $restore->fresh(['site.server', 'sourceBackup', 'triggeredBy']);
    }

    protected function currentReleasePath(Site $site): string
    {
        return filled($site->current_release_path)
            ? rtrim((string) $site->current_release_path, '/')
            : '';
    }

    protected function backupRoot(Site $site): string
    {
        return rtrim((string) $site->deploy_path, '/').'/backups';
    }

    protected function restoreReleasePath(Site $site, SiteBackup $backup): string
    {
        return sprintf(
            '%s/releases/%s-%s',
            rtrim((string) $site->deploy_path, '/'),
            now()->utc()->format('YmdHis'),
            $backup->id,
        );
    }

    protected function backupSnapshotPath(Site $site, SiteBackup $backup): string
    {
        return sprintf(
            '%s/%s-%s',
            $this->backupRoot($site),
            now()->utc()->format('YmdHis'),
            $backup->id,
        );
    }

    protected function copyPath(Site $site, string $sourcePath, string $destinationPath): string
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        return match ($server->connection_type) {
            'local' => $this->copyLocalPath($sourcePath, $destinationPath),
            'cpanel' => $this->copyCpanelPath($server, $site, $sourcePath, $destinationPath),
            default => $this->copyRemotePath($server, $sourcePath, $destinationPath),
        };
    }

    protected function syncAndActivate(Site $site, string $releasePath): string
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        return match ($server->connection_type) {
            'local' => $this->syncLocalRuntime($site, $releasePath),
            'cpanel' => implode(PHP_EOL, array_merge(
                $this->cpanelSiteProvisioner->syncSharedRuntime($site, $releasePath),
                $this->cpanelSiteProvisioner->activateRelease($site, $releasePath),
            )),
            default => $this->syncRemoteRuntime($site, $releasePath),
        };
    }

    protected function copyLocalPath(string $sourcePath, string $destinationPath): string
    {
        File::ensureDirectoryExists(dirname($destinationPath));
        File::deleteDirectory($destinationPath);
        File::copyDirectory($sourcePath, $destinationPath);

        return sprintf('Copied local snapshot from %s to %s.', $sourcePath, $destinationPath);
    }

    protected function copyRemotePath($server, string $sourcePath, string $destinationPath): string
    {
        $commands = [
            sprintf('mkdir -p %s', escapeshellarg(dirname($destinationPath))),
            sprintf('rm -rf %s', escapeshellarg($destinationPath)),
            sprintf('cp -a %s %s', escapeshellarg($sourcePath), escapeshellarg($destinationPath)),
        ];

        $output = [];

        foreach ($commands as $command) {
            $process = $this->sshCommandRunner->execute($server, $command);

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Unable to copy the backup snapshot.');
            }

            $output[] = trim($process->getOutput() ?: $process->getErrorOutput() ?: $command);
        }

        return implode(PHP_EOL, array_filter($output));
    }

    protected function copyCpanelPath($server, Site $site, string $sourcePath, string $destinationPath): string
    {
        $this->cpanelSiteProvisioner->ensureDirectoryTree($site, dirname($destinationPath));
        $this->cpanelApiClient->copyPath($server, $sourcePath, $destinationPath);

        return sprintf('Copied cPanel snapshot from %s to %s.', $sourcePath, $destinationPath);
    }

    protected function syncRemoteRuntime(Site $site, string $releasePath): string
    {
        $commands = array_merge(
            $this->releaseManager->syncSharedRuntimeCommands($site),
            $this->releaseManager->linkSharedRuntimeCommands($site, $releasePath),
            [
                sprintf('ln -sfn %s %s', escapeshellarg($releasePath), escapeshellarg(rtrim((string) $site->deploy_path, '/').'/current')),
            ],
        );

        $output = [];

        foreach ($commands as $command) {
            $process = $this->sshCommandRunner->execute($site->server, $command);

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Unable to restore the backup snapshot.');
            }

            $output[] = trim($process->getOutput() ?: $process->getErrorOutput() ?: $command);
        }

        $site->update([
            'current_release_path' => $releasePath,
        ]);

        return implode(PHP_EOL, array_filter($output));
    }

    protected function syncLocalRuntime(Site $site, string $releasePath): string
    {
        $basePath = rtrim((string) $site->deploy_path, '/');
        $sharedPath = $basePath.'/shared';

        File::ensureDirectoryExists($sharedPath.'/storage');
        File::ensureDirectoryExists($sharedPath.'/bootstrap/cache');
        File::put($sharedPath.'/.env', $this->releaseManager->environmentFileContents($site));

        foreach ($this->releaseManager->sharedFiles($site) as $file) {
            $filePath = $sharedPath.'/'.$file['path'];
            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $file['contents']);
        }

        $this->linkLocalPath($sharedPath.'/.env', $releasePath.'/.env');
        $this->linkLocalPath($sharedPath.'/storage', $releasePath.'/storage');

        foreach ($this->releaseManager->sharedFiles($site) as $file) {
            $this->linkLocalPath($sharedPath.'/'.$file['path'], $releasePath.'/'.$file['path']);
        }

        $site->update([
            'current_release_path' => $releasePath,
        ]);

        return sprintf('Shared runtime synced and release %s activated locally.', $releasePath);
    }

    protected function linkLocalPath(string $sourcePath, string $destinationPath): void
    {
        if (is_dir($destinationPath) && ! is_link($destinationPath)) {
            File::deleteDirectory($destinationPath);
        } elseif (file_exists($destinationPath) || is_link($destinationPath)) {
            @unlink($destinationPath);
        }

        File::ensureDirectoryExists(dirname($destinationPath));

        if (@symlink($sourcePath, $destinationPath)) {
            return;
        }

        if (is_dir($sourcePath)) {
            File::copyDirectory($sourcePath, $destinationPath);

            return;
        }

        File::copy($sourcePath, $destinationPath);
    }

    protected function snapshotSize(Site $site, string $path): ?int
    {
        if (($site->server?->connection_type ?? null) !== 'local') {
            return null;
        }

        if (! File::exists($path)) {
            return null;
        }

        return collect(File::allFiles($path))
            ->reduce(function (int $total, $file): int {
                return $total + (int) $file->getSize();
            }, 0);
    }

    protected function snapshotChecksum(Site $site, string $path): ?string
    {
        if (($site->server?->connection_type ?? null) !== 'local') {
            return null;
        }

        if (! File::exists($path)) {
            return null;
        }

        $hash = hash_init('sha256');

        foreach (collect(File::allFiles($path)) as $file) {
            hash_update($hash, $file->getRelativePathname());
            hash_update($hash, (string) $file->getSize());
            hash_update($hash, (string) $file->getMTime());
            hash_update($hash, (string) File::get($file->getPathname()));
        }

        return hash_final($hash);
    }
}
