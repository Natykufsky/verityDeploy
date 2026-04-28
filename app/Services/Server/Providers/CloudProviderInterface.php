<?php

namespace App\Services\Server\Providers;

interface CloudProviderInterface
{
    public function providerType(): string;

    public function isConfigured(): bool;

    public function listRegions(): array;

    public function listSizes(?string $region = null): array;

    public function createServer(array $config): array;

    public function getServer(string $id): ?array;

    public function deleteServer(string $id): bool;

    public function getServerIp(string $id): ?string;

    public function getServerStatus(string $id): ?string;

    public function getOrCreateSshKey(string $name, string $publicKey): ?string;

    public function deleteSshKey(string $id): bool;
}
