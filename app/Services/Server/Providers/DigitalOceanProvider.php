<?php

namespace App\Services\Server\Providers;

class DigitalOceanProvider extends CloudProvider
{
    public function providerType(): string
    {
        return 'digitalocean';
    }

    protected function apiClient(): mixed
    {
        return null;
    }

    protected function credentials(): array
    {
        return [];
    }
}
