<?php

namespace App\Services\Server\Providers;

class HetznerProvider extends CloudProvider
{
    public function providerType(): string
    {
        return 'hetzner';
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
