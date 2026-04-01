<?php

namespace App\Services\Deployment;

use App\Models\ReleaseCleanupRun;
use App\Models\Site;
use App\Services\SSH\SshCommandRunner;
use App\Support\SiteEnvironmentPreview;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ReleaseManager
{
    public function __construct(
        protected SshCommandRunner $sshCommandRunner,
    ) {}

    /**
     * @return array<int, string>
     */
    public function bootstrapCommands(Site $site): array
    {
        $basePath = $this->basePath($site);

        return [
            sprintf(
                'mkdir -p %s %s %s',
                escapeshellarg($basePath.'/releases'),
                escapeshellarg($basePath.'/shared'),
                escapeshellarg($basePath.'/backups'),
            ),
            ...$this->syncSharedRuntimeCommands($site),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function syncSharedRuntimeCommands(Site $site): array
    {
        $sharedPath = $this->sharedPath($site);
        $commands = [
            sprintf(
                'mkdir -p %s %s',
                escapeshellarg($sharedPath.'/storage'),
                escapeshellarg($sharedPath.'/bootstrap/cache'),
            ),
            sprintf(
                'touch %s',
                escapeshellarg($sharedPath.'/.env'),
            ),
            sprintf(
                'printf %s > %s',
                escapeshellarg($this->environmentFileContents($site)),
                escapeshellarg($sharedPath.'/.env'),
            ),
        ];

        foreach ($this->sharedFiles($site) as $file) {
            $filePath = $sharedPath.'/'.$file['path'];
            $commands[] = sprintf(
                'mkdir -p %s && printf %s > %s',
                escapeshellarg(dirname($filePath)),
                escapeshellarg($file['contents']),
                escapeshellarg($filePath),
            );
        }

        $commands[] = sprintf(
            'chmod -R ug+rwX %s',
            escapeshellarg($sharedPath),
        );

        return $commands;
    }

    /**
     * @return array<int, string>
     */
    public function linkSharedRuntimeCommands(Site $site, string $releasePath): array
    {
        $sharedPath = $this->sharedPath($site);
        $commands = [
            sprintf(
                'ln -sfn %s %s',
                escapeshellarg($sharedPath.'/.env'),
                escapeshellarg($releasePath.'/.env'),
            ),
            sprintf(
                'ln -sfn %s %s',
                escapeshellarg($sharedPath.'/storage'),
                escapeshellarg($releasePath.'/storage'),
            ),
        ];

        foreach ($this->sharedFiles($site) as $file) {
            $sharedFilePath = $sharedPath.'/'.$file['path'];
            $releaseFilePath = $releasePath.'/'.$file['path'];

            $commands[] = sprintf(
                'mkdir -p %s && ln -sfn %s %s',
                escapeshellarg(dirname($releaseFilePath)),
                escapeshellarg($sharedFilePath),
                escapeshellarg($releaseFilePath),
            );
        }

        return $commands;
    }

    public function cleanupCommand(Site $site, int $keep = 5): string
    {
        $basePath = $this->basePath($site);
        $keep = max(1, $keep);

        return sprintf(
            'php -r "\$base = %s; \$keep = %d; \$releases = glob(\$base . \'/releases/*\', GLOB_ONLYDIR) ?: []; sort(\$releases); \$delete = array_slice(\$releases, 0, max(0, count(\$releases) - \$keep)); foreach (\$delete as \$release) { if (is_dir(\$release)) { exec(\'rm -rf \' . escapeshellarg(\$release)); } }"',
            escapeshellarg($basePath),
            $keep,
        );
    }

    public function cleanupOldReleases(Site $site, int $keep = 5): Process
    {
        $run = ReleaseCleanupRun::query()->create([
            'site_id' => $site->id,
            'status' => 'running',
            'keep_count' => $keep,
            'started_at' => now(),
        ]);

        try {
            $process = $this->sshCommandRunner->execute($site->server, $this->cleanupCommand($site, $keep));
            $output = trim(($process->getOutput() ?: '').PHP_EOL.($process->getErrorOutput() ?: ''));

            $run->update([
                'status' => $process->isSuccessful() ? 'successful' : 'failed',
                'output' => $output ?: null,
                'error_message' => $process->isSuccessful() ? null : trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Unable to rotate old releases.'),
                'finished_at' => now(),
            ]);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Unable to rotate old releases.');
            }

            return $process;
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }
    }

    public function environmentFileContents(Site $site): string
    {
        return SiteEnvironmentPreview::build(
            $this->environmentVariables($site),
            $site->shared_env_contents,
        )['effective_contents'];
    }

    /**
     * @return array<int, array{path: string, contents: string}>
     */
    public function sharedFiles(Site $site): array
    {
        return collect($site->shared_files ?? [])
            ->map(function (mixed $file): array {
                $path = $this->normalizeRelativePath((string) data_get($file, 'path', ''));

                return [
                    'path' => $path,
                    'contents' => (string) data_get($file, 'contents', ''),
                ];
            })
            ->filter(fn (array $file): bool => filled($file['path']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function environmentVariables(Site $site): array
    {
        return collect($site->environment_variables ?? [])
            ->mapWithKeys(function (mixed $value, mixed $key): array {
                $key = trim((string) $key);

                if ($key === '') {
                    return [];
                }

                return [$key => $value];
            })
            ->all();
    }

    protected function basePath(Site $site): string
    {
        return rtrim((string) $site->deploy_path, '/');
    }

    protected function sharedPath(Site $site): string
    {
        return $this->basePath($site).'/shared';
    }

    protected function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || Str::contains($path, '..')) {
            return '';
        }

        return $path;
    }
}
