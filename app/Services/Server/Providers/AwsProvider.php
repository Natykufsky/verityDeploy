<?php

namespace App\Services\Server\Providers;

class AwsProvider extends CloudProvider
{
    public function providerType(): string
    {
        return 'aws';
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
