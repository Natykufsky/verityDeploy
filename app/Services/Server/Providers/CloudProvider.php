<?php

namespace App\Services\Server\Providers;

abstract class CloudProvider implements CloudProviderInterface
{
    abstract protected function apiClient(): mixed;

    abstract protected function credentials(): array;

    public function isConfigured(): bool
    {
        $creds = $this->credentials();

        return ! empty($creds);
    }

    public function listRegions(): array
    {
        return [];
    }

    public function listSizes(?string $region = null): array
    {
        return [];
    }

    public function createServer(array $config): array
    {
        return [];
    }

    public function getServer(string $id): ?array
    {
        return null;
    }

    public function deleteServer(string $id): bool
    {
        return false;
    }

    public function getServerIp(string $id): ?string
    {
        $server = $this->getServer($id);

        return $server['ip_address'] ?? null;
    }

    public function getServerStatus(string $id): ?string
    {
        $server = $this->getServer($id);

        return $server['status'] ?? null;
    }

    public function getOrCreateSshKey(string $name, string $publicKey): ?string
    {
        return null;
    }

    public function deleteSshKey(string $id): bool
    {
        return false;
    }

    protected function mapServer(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'ip_address' => $this->extractIp($data),
            'status' => $this->extractStatus($data),
            'region' => $this->extractRegion($data),
            'created_at' => $data['created_at'] ?? null,
            'metadata' => $data,
        ];
    }

    protected function extractIp(array $data): ?string
    {
        if (isset($data['networks']['v4'])) {
            foreach ($data['networks']['v4'] as $network) {
                if (($network['type'] ?? '') === 'public') {
                    return $network['ip_address'] ?? null;
                }
            }
        }

        return $data['ip_address'] ?? $data['public_ip_address'] ?? null;
    }

    protected function extractStatus(array $data): string
    {
        return $data['status'] ?? 'unknown';
    }

    protected function extractRegion(array $data): ?string
    {
        return $data['region'] ?? $data['region_slug'] ?? null;
    }
}
