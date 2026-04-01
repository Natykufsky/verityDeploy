<?php

namespace App\Services\Cpanel;

use App\Models\Deployment;
use App\Models\Site;
use App\Services\Deployment\ReleaseManager;
use RuntimeException;

class CpanelSiteProvisioner
{
    public function __construct(
        protected CpanelApiClient $client,
        protected ReleaseManager $releaseManager,
    ) {}

    /**
     * @return array<int, string>
     */
    public function bootstrap(Site $site): array
    {
        $server = $site->server;

        if (blank($server)) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('The cPanel provisioner can only bootstrap cPanel servers.');
        }

        if (blank($site->deploy_path)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        $summary = [];

        $this->client->ping($server);
        $summary[] = 'Validated the cPanel API connection.';

        $this->ensureWorkspace($site);
        $summary[] = sprintf('Ensured the workspace exists at %s.', rtrim($site->deploy_path, '/'));

        $sharedSummary = $this->syncSharedRuntime($site);
        $summary[] = 'Wrote the shared environment file and runtime files.';
        $summary = array_merge($summary, $sharedSummary);

        return $summary;
    }

    public function ensureWorkspace(Site $site): void
    {
        $server = $site->server;
        $basePath = rtrim((string) $site->deploy_path, '/');

        $this->ensureDirectory($server, $basePath);
        $this->ensureDirectory($server, $basePath.'/shared');
        $this->ensureDirectory($server, $basePath.'/shared/storage');
        $this->ensureDirectory($server, $basePath.'/shared/bootstrap/cache');
        $this->ensureDirectory($server, $basePath.'/releases');
        $this->ensureDirectory($server, $basePath.'/backups');
    }

    public function buildReleasePath(Deployment $deployment): string
    {
        $basePath = rtrim((string) $deployment->site->deploy_path, '/');
        $stamp = now()->utc()->format('YmdHis');

        return sprintf('%s/releases/%s-%s', $basePath, $stamp, $deployment->id);
    }

    public function ensureReleaseDirectory(Site $site, string $releasePath): void
    {
        $this->ensureDirectory($site->server, $releasePath);
    }

    public function ensureDirectoryTree(Site $site, string $path): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return;
        }

        $isAbsolute = str_starts_with($path, '/');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        $current = $isAbsolute ? '/' : '';

        foreach ($segments as $segment) {
            $current = $current === '/' || $current === '' ? $current.$segment : $current.'/'.$segment;
            $this->ensureDirectory($site->server, $current);
        }
    }

    /**
     * @return array<int, string>
     */
    public function syncSharedRuntime(Site $site, ?string $releasePath = null): array
    {
        $server = $site->server;
        $basePath = rtrim((string) $site->deploy_path, '/');
        $sharedPath = $basePath.'/shared';
        $summary = [];

        $this->ensureDirectory($server, $sharedPath);
        $this->ensureDirectory($server, $sharedPath.'/storage');
        $this->ensureDirectory($server, $sharedPath.'/bootstrap/cache');

        $environmentFile = $this->releaseManager->environmentFileContents($site);

        $this->client->saveFile($server, $sharedPath, '.env', $environmentFile);
        $summary[] = 'Updated shared .env.';

        foreach ($this->releaseManager->sharedFiles($site) as $file) {
            $filePath = $sharedPath.'/'.$file['path'];
            $directory = dirname($filePath);
            $filename = basename($filePath);

            $this->ensureDirectory($server, $directory);
            $this->client->saveFile($server, $directory, $filename, $file['contents']);
            $summary[] = sprintf('Updated shared file %s.', $file['path']);
        }

        if (filled($releasePath)) {
            $releasePath = rtrim($releasePath, '/');
            $this->linkSharedPath($server, $sharedPath.'/.env', $releasePath.'/.env');
            $this->linkSharedPath($server, $sharedPath.'/storage', $releasePath.'/storage');
            $summary[] = 'Linked shared .env and storage into the active release.';

            foreach ($this->releaseManager->sharedFiles($site) as $file) {
                $sharedFilePath = $sharedPath.'/'.$file['path'];
                $releaseFilePath = $releasePath.'/'.$file['path'];

                $this->ensureDirectory($server, dirname($releaseFilePath));
                $this->linkSharedPath($server, $sharedFilePath, $releaseFilePath);
                $summary[] = sprintf('Linked shared file %s into the active release.', $file['path']);
            }
        }

        return $summary;
    }

    public function activateRelease(Site $site, string $releasePath): array
    {
        $server = $site->server;
        $basePath = rtrim((string) $site->deploy_path, '/');
        $currentPath = $basePath.'/current';

        $this->linkSharedPath($server, $releasePath, $currentPath);

        $site->update([
            'current_release_path' => $releasePath,
        ]);

        return [
            sprintf('Activated release %s.', $releasePath),
        ];
    }

    protected function ensureDirectory($server, string $path): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return;
        }

        try {
            $this->client->mkdir($server, dirname($path), basename($path));
        } catch (RuntimeException) {
            // Directory already exists or cannot be created; continue with the next path.
        }
    }

    protected function linkSharedPath($server, string $sourcePath, string $destinationPath): void
    {
        try {
            $this->client->unlinkPath($server, $destinationPath);
        } catch (RuntimeException) {
            // Ignore missing links or files.
        }

        $this->client->linkPath($server, $sourcePath, $destinationPath);
    }
}
