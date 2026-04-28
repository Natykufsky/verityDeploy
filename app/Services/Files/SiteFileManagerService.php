<?php

namespace App\Services\Files;

use App\Models\Site;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class SiteFileManagerService
{
    public function __construct(
        protected SshCommandRunner $sshCommandRunner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function browse(Site $site, string $relativePath = ''): array
    {
        $site->loadMissing('server');
        $rootPath = $this->rootPath($site);

        if (blank($rootPath)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->resolveAbsolutePath($rootPath, $relativePath);

        return [
            'root_path' => $rootPath,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'breadcrumbs' => $this->breadcrumbs($relativePath),
            'items' => $this->listItems($site, $absolutePath),
        ];
    }

    public function read(Site $site, string $relativePath): array
    {
        $site->loadMissing('server');
        $rootPath = $this->rootPath($site);

        if (blank($rootPath)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->resolveAbsolutePath($rootPath, $relativePath);

        if ($this->isDirectory($site, $absolutePath)) {
            throw new RuntimeException('The selected path is a directory, not a file.');
        }

        return [
            'root_path' => $rootPath,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'contents' => $this->readFileContents($site, $absolutePath),
        ];
    }

    public function save(Site $site, string $relativePath, string $contents): array
    {
        $site->loadMissing('server');
        $rootPath = $this->rootPath($site);

        if (blank($rootPath)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->resolveAbsolutePath($rootPath, $relativePath);

        if ($this->isDirectory($site, $absolutePath)) {
            throw new RuntimeException('Directories cannot be edited as files.');
        }

        $this->writeFileContents($site, $absolutePath, $contents);

        return $this->read($site, $relativePath);
    }

    protected function rootPath(Site $site): ?string
    {
        if (filled($site->current_release_path)) {
            return rtrim((string) $site->current_release_path, '/');
        }

        if (blank($site->deploy_path)) {
            return null;
        }

        return rtrim((string) $site->deploy_path, '/').'/current';
    }

    protected function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || $relativePath === '.') {
            return '';
        }

        $parts = [];

        foreach (explode('/', $relativePath) as $part) {
            $part = trim($part);

            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    protected function resolveAbsolutePath(string $rootPath, string $relativePath): string
    {
        $absolute = rtrim($rootPath, '/');

        if ($relativePath !== '') {
            $absolute .= '/'.$relativePath;
        }

        return $absolute;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listItems(Site $site, string $absolutePath): array
    {
        if ($site->server?->connection_type === 'local') {
            return $this->listLocalItems($absolutePath);
        }

        return $this->listRemoteItems($site, $absolutePath);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listLocalItems(string $absolutePath): array
    {
        if (! File::isDirectory($absolutePath)) {
            return [];
        }

        return collect(File::directories($absolutePath))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => $path,
                'relative_path' => trim(str_replace('\\', '/', Str::after($path, $absolutePath)), '/'),
                'type' => 'directory',
                'size' => null,
                'modified_at' => now()->setTimestamp((int) filemtime($path))->toDateTimeString(),
            ])
            ->merge(
                collect(File::files($absolutePath))->map(fn ($file): array => [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'relative_path' => trim(str_replace('\\', '/', Str::after($file->getPathname(), $absolutePath)), '/'),
                    'type' => 'file',
                    'size' => $file->getSize(),
                    'modified_at' => now()->setTimestamp((int) $file->getMTime())->toDateTimeString(),
                ]),
            )
            ->sortBy(fn (array $item): string => strtolower($item['name']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listRemoteItems(Site $site, string $absolutePath): array
    {
        $payload = $this->runRemoteJsonScript($site, <<<'PHP'
$root = $argv[1] ?? '';
if ($root === '' || ! is_dir($root)) {
    echo json_encode(['items' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
}

$items = [];
foreach (new DirectoryIterator($root) as $file) {
    if ($file->isDot()) {
        continue;
    }

    $items[] = [
        'name' => $file->getFilename(),
        'relative_path' => $file->getFilename(),
        'type' => $file->isDir() ? 'directory' : 'file',
        'size' => $file->isDir() ? null : $file->getSize(),
        'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
    ];
}

usort($items, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

echo json_encode(['items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP, [$absolutePath]);

        return $payload['items'] ?? [];
    }

    protected function isDirectory(Site $site, string $absolutePath): bool
    {
        if ($site->server?->connection_type === 'local') {
            return File::isDirectory($absolutePath);
        }

        $payload = $this->runRemoteJsonScript($site, <<<'PHP'
$path = $argv[1] ?? '';
echo json_encode(['is_directory' => is_dir($path)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP, [$absolutePath]);

        return (bool) ($payload['is_directory'] ?? false);
    }

    protected function readFileContents(Site $site, string $absolutePath): string
    {
        if ($site->server?->connection_type === 'local') {
            if (! File::exists($absolutePath)) {
                throw new RuntimeException('The selected file does not exist.');
            }

            return (string) File::get($absolutePath);
        }

        $payload = $this->runRemoteJsonScript($site, <<<'PHP'
$path = $argv[1] ?? '';
if (! is_file($path)) {
    echo json_encode(['contents' => null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
}

echo json_encode(['contents' => base64_encode((string) file_get_contents($path))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP, [$absolutePath]);

        $encoded = (string) ($payload['contents'] ?? '');

        if ($encoded === '') {
            throw new RuntimeException('The selected file does not exist.');
        }

        return (string) base64_decode($encoded, true);
    }

    protected function writeFileContents(Site $site, string $absolutePath, string $contents): void
    {
        if ($site->server?->connection_type === 'local') {
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $contents);

            return;
        }

        $this->runRemoteJsonScript($site, <<<'PHP'
$path = $argv[1] ?? '';
$encoded = $argv[2] ?? '';
$content = base64_decode($encoded, true);

if ($content === false) {
    fwrite(STDERR, 'Unable to decode file contents.');
    exit(1);
}

$directory = dirname($path);
if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
    fwrite(STDERR, 'Unable to create the target directory.');
    exit(1);
}

if (file_put_contents($path, $content) === false) {
    fwrite(STDERR, 'Unable to write the file.');
    exit(1);
}

echo json_encode(['saved' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP, [$absolutePath, base64_encode($contents)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function runRemoteJsonScript(Site $site, string $script, array $arguments = []): array
    {
        $server = $site->server;

        if (! $server) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        $command = sprintf(
            'php -r %s%s',
            escapeshellarg($script),
            $arguments !== [] ? ' '.implode(' ', array_map(static fn (string $argument): string => escapeshellarg($argument), $arguments)) : '',
        );

        $result = $this->sshCommandRunner->run($server, $command);

        if ((int) ($result['exit_code'] ?? 1) !== 0) {
            throw new RuntimeException(trim((string) ($result['output'] ?? '')) ?: 'Unable to access the remote file system.');
        }

        $payload = json_decode((string) ($result['output'] ?? '{}'), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Unable to parse the remote file manager response.');
        }

        return $payload;
    }

    /**
     * @return array<int, array{label: string, path: string}>
     */
    protected function breadcrumbs(string $relativePath): array
    {
        $breadcrumbs = [
            ['label' => 'Root', 'path' => ''],
        ];

        if ($relativePath === '') {
            return $breadcrumbs;
        }

        $parts = explode('/', $relativePath);
        $current = [];

        foreach ($parts as $part) {
            $current[] = $part;
            $breadcrumbs[] = [
                'label' => $part,
                'path' => implode('/', $current),
            ];
        }

        return $breadcrumbs;
    }
}
