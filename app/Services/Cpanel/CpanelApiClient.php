<?php

namespace App\Services\Cpanel;

use App\Models\Server;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CpanelApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function request(Server $server, string $module, string $function, array $query = []): array
    {
        $response = $this->client($server)
            ->get(sprintf('%s/%s', $module, $function), $query);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cPanel API returned an invalid response.');
        }

        $status = data_get($payload, 'result.status');

        if ($status === null) {
            $status = data_get($payload, 'status');
        }

        if (! $status) {
            $messages = data_get($payload, 'result.errors')
                ?? data_get($payload, 'result.messages')
                ?? data_get($payload, 'errors')
                ?? data_get($payload, 'messages')
                ?? [];

            if (is_array($messages) && filled($messages)) {
                throw new RuntimeException(implode(' ', array_map('strval', $messages)));
            }

            throw new RuntimeException('cPanel API request failed.');
        }

        $data = data_get($payload, 'result.data');

        if ($data === null) {
            $data = data_get($payload, 'data');
        }

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestApi2(Server $server, string $module, string $function, array $query = []): array
    {
        $response = $this->api2Client($server)
            ->asForm()
            ->post('', array_merge([
                'cpanel_jsonapi_user' => $this->cpanelUsername($server),
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => $module,
                'cpanel_jsonapi_func' => $function,
            ], $query));

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cPanel API 2 returned an invalid response.');
        }

        if (! data_get($payload, 'cpanelresult.event.result')) {
            $reason = data_get($payload, 'cpanelresult.event.reason')
                ?? data_get($payload, 'cpanelresult.data.0.reason')
                ?? data_get($payload, 'cpanelresult.data.reason');

            if (filled($reason)) {
                throw new RuntimeException((string) $reason);
            }

            throw new RuntimeException('cPanel API 2 request failed.');
        }

        $data = data_get($payload, 'cpanelresult.data');

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(Server $server): array
    {
        return $this->request($server, 'Tokens', 'list');
    }

    public function discoverSshPort(Server $server): int
    {
        $data = $this->request($server, 'SSH', 'get_port');
        $port = data_get($data, 'port')
            ?? data_get($data, 'value')
            ?? data_get($data, 'ssh_port');

        if (! is_numeric($port)) {
            throw new RuntimeException('The cPanel API did not return a valid SSH port.');
        }

        return (int) $port;
    }

    /**
     * @return array<string, mixed>
     */
    public function listDomains(Server $server): array
    {
        return $this->request($server, 'DomainInfo', 'list_domains');
    }

    /**
     * @return array<string, mixed>
     */
    public function setHttpsRedirect(Server $server, string $domain, bool $state): array
    {
        return $this->request($server, 'DomainInfo', 'set_https_redirect', [
            'domain' => $domain,
            'enabled' => $state ? 1 : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkAutoSsl(Server $server): array
    {
        // For a specific user context (already set by Token/Username)
        return $this->request($server, 'SSL', 'autossl_check_for_user', [
            'user' => $this->cpanelUsername($server),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function mkdir(Server $server, string $path, string $name, ?string $permissions = null): array
    {
        $query = [
            'path' => $path,
            'name' => $name,
        ];

        if (filled($permissions)) {
            $query['permissions'] = $permissions;
        }

        return $this->requestApi2($server, 'Fileman', 'mkdir', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function addAddonDomain(Server $server, string $domain, string $subdomain, string $directory): array
    {
        return $this->requestApi2($server, 'AddonDomain', 'addaddondomain', [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $directory,
            'ftp_is_optional' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function addSubdomain(Server $server, string $domain, string $rootDomain, string $directory): array
    {
        return $this->requestApi2($server, 'SubDomain', 'addsubdomain', [
            'domain' => $domain,
            'rootdomain' => $rootDomain,
            'dir' => $directory,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function parkDomain(Server $server, string $domain, string $topDomain): array
    {
        return $this->requestApi2($server, 'Park', 'park', [
            'domain' => $domain,
            'topdomain' => $topDomain,
            'disallowdot' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function saveFile(Server $server, string $path, string $filename, string $content): array
    {
        return $this->client($server)
            ->asForm()
            ->post('Fileman/save_file_content', [
                'dir' => $path,
                'file' => $filename,
                'content' => $content,
            ])
            ->throwIf(fn (Response $response): bool => $response->failed())
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadFile(Server $server, string $directory, string $path, ?string $filename = null): array
    {
        $filename ??= basename($path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read the file at [{$path}].");
        }

        $response = $this->api2Client($server)
            ->asMultipart()
            ->attach('file-1', $contents, $filename)
            ->post('', [
                'cpanel_jsonapi_user' => $this->cpanelUsername($server),
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Fileman',
                'cpanel_jsonapi_func' => 'uploadfiles',
                'dir' => $directory,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cPanel upload_files returned an invalid response.');
        }

        if (! data_get($payload, 'cpanelresult.event.result')) {
            $reason = data_get($payload, 'cpanelresult.error')
                ?? data_get($payload, 'cpanelresult.data.0.uploads.0.reason')
                ?? data_get($payload, 'cpanelresult.data.0.reason');

            if (filled($reason)) {
                throw new RuntimeException((string) $reason);
            }

            throw new RuntimeException('Unable to upload the file to cPanel.');
        }

        $data = data_get($payload, 'cpanelresult.data');

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractArchive(Server $server, string $archivePath, string $destinationDirectory, string $metadata = 'tar.gz'): array
    {
        return $this->fileOp($server, 'extract', [
            'sourcefiles' => $this->toHomeRelativePath($server, $archivePath),
            'destfiles' => $this->toHomeRelativePath($server, $destinationDirectory),
            'doubledecode' => 1,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function linkPath(Server $server, string $sourcePath, string $destinationPath): array
    {
        return $this->fileOp($server, 'link', [
            'sourcefiles' => $this->toHomeRelativePath($server, $sourcePath),
            'destfiles' => $this->toHomeRelativePath($server, $destinationPath),
            'doubledecode' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function unlinkPath(Server $server, string $destinationPath): array
    {
        return $this->fileOp($server, 'unlink', [
            'sourcefiles' => $this->toHomeRelativePath($server, $destinationPath),
            'doubledecode' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function copyPath(Server $server, string $sourcePath, string $destinationPath): array
    {
        return $this->fileOp($server, 'copy', [
            'sourcefiles' => $this->toHomeRelativePath($server, $sourcePath),
            'destfiles' => $this->toHomeRelativePath($server, $destinationPath),
            'doubledecode' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function fileOp(Server $server, string $operation, array $query = []): array
    {
        $response = $this->api2Client($server)
            ->asForm()
            ->post('', array_merge([
                'cpanel_jsonapi_user' => $this->cpanelUsername($server),
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Fileman',
                'cpanel_jsonapi_func' => 'fileop',
                'op' => $operation,
            ], $query));

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cPanel file operation returned an invalid response.');
        }

        if (! data_get($payload, 'cpanelresult.event.result')) {
            $reason = data_get($payload, 'cpanelresult.event.reason')
                ?? data_get($payload, 'cpanelresult.data.0.reason')
                ?? data_get($payload, 'cpanelresult.data.reason');

            if (filled($reason)) {
                throw new RuntimeException((string) $reason);
            }

            throw new RuntimeException('cPanel file operation failed.');
        }

        $data = data_get($payload, 'cpanelresult.data');

        return is_array($data) ? $data : ['value' => $data];
    }

    public function homeDirectory(Server $server): string
    {
        return sprintf('/home/%s', trim((string) $server->effectiveSshUser()));
    }

    public function toHomeRelativePath(Server $server, string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $home = rtrim($this->homeDirectory($server), '/');

        if (str_starts_with($path, $home.'/')) {
            return ltrim(substr($path, strlen($home) + 1), '/');
        }

        if (str_starts_with($path, $home)) {
            return ltrim(substr($path, strlen($home)), '/');
        }

        return ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureRepository(Server $server, string $repositoryRoot, string $repositoryName, ?string $sourceRepository = null): array
    {
        $query = [
            'repository_root' => $repositoryRoot,
            'name' => $repositoryName,
            'type' => 'git',
        ];

        if (filled($sourceRepository)) {
            $query['source_repository'] = json_encode([
                'remote_name' => 'origin',
                'url' => $sourceRepository,
            ]);
        }

        return $this->request($server, 'VersionControl', 'create', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateRepository(Server $server, string $repositoryRoot, ?string $branch = null, ?string $name = null): array
    {
        $query = [
            'repository_root' => $repositoryRoot,
        ];

        if (filled($branch)) {
            $query['branch'] = $branch;
        }

        if (filled($name)) {
            $query['name'] = $name;
        }

        return $this->request($server, 'VersionControl', 'update', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRepository(Server $server, string $repositoryRoot): array
    {
        return $this->request($server, 'VersionControl', 'retrieve', [
            'repository_root' => $repositoryRoot,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDeployment(Server $server, string $repositoryRoot): array
    {
        return $this->request($server, 'VersionControlDeployment', 'create', [
            'repository_root' => $repositoryRoot,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeployment(Server $server, string $repositoryRoot): array
    {
        return $this->request($server, 'VersionControlDeployment', 'retrieve', [
            'repository_root' => $repositoryRoot,
        ]);
    }

    protected function client(Server $server)
    {
        $port = $server->effectiveCpanelApiPort() ?: 2083;
        $prefix = ($port == 2087) ? 'whm' : 'cpanel';

        $baseUrl = sprintf(
            'https://%s:%d/execute',
            $server->ip_address,
            $port,
        );

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders([
                'Authorization' => "{$prefix} ".$this->cpanelUsername($server).':'.(string) $server->effectiveCpanelApiToken(),
            ]);
    }

    protected function api2Client(Server $server)
    {
        $port = $server->effectiveCpanelApiPort() ?: 2083;
        $prefix = ($port == 2087) ? 'whm' : 'cpanel';

        $baseUrl = sprintf(
            'https://%s:%d/json-api/cpanel',
            $server->ip_address,
            $port,
        );

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders([
                'Authorization' => "{$prefix} ".$this->cpanelUsername($server).':'.(string) $server->effectiveCpanelApiToken(),
            ]);
    }

    protected function cpanelUsername(Server $server): string
    {
        return trim((string) ($server->effectiveCpanelUsername() ?: $server->effectiveSshUser()));
    }

    protected function errorMessage(Response $response): string
    {
        $message = trim($response->body());

        if ($message === '') {
            return 'Unable to reach the cPanel API.';
        }

        $lower = strtolower($message);

        if (str_contains($lower, 'login is invalid')) {
            return 'cPanel rejected the login. The username/token pair is wrong for this account, or the token does not belong to the cPanel account you entered.';
        }

        if (str_contains($lower, 'unauthorized') || str_contains($lower, 'forbidden')) {
            return 'cPanel rejected the API token. Confirm the token belongs to the same cPanel account as the cPanel username and that the API port is correct.';
        }

        if (str_contains($lower, 'not found') || str_contains($lower, '404')) {
            return 'The cPanel API endpoint was not found. Confirm the API port and that you are pointing at the cPanel host, not the website port.';
        }

        if (str_contains($lower, 'wrong version number') || str_contains($lower, 'tls connect error')) {
            return sprintf(
                'The cPanel API port is wrong or not serving HTTPS. Port %s looks like an SSH port, not the cPanel HTTPS/API port. Change the cPanel API port to the real cPanel service port, usually 2083, and retry.',
                $response->effectiveUri()?->getPort() ?: 'unknown',
            );
        }

        if (str_contains($lower, 'unable to reach the cpanel api')) {
            return 'The cPanel API did not respond. Confirm that port 2083 is open on the host, the account has cPanel API access, and the hostname resolves to the cPanel service.';
        }

        return $message;
    }
}
