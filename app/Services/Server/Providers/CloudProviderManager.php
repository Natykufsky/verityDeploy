<?php

namespace App\Services\Server\Providers;

class CloudProviderManager
{
    protected static array $providers = [];

    public static function register(string $providerType, string $class): void
    {
        static::$providers[$providerType] = $class;
    }

    public static function make(string $providerType): ?CloudProviderInterface
    {
        if (isset(static::$providers[$providerType])) {
            $class = static::$providers[$providerType];

            return app($class);
        }

        $class = match ($providerType) {
            'digitalocean' => DigitalOceanProvider::class,
            'aws' => AwsProvider::class,
            'hetzner' => HetznerProvider::class,
            default => null,
        };

        if (! $class || ! class_exists($class)) {
            return null;
        }

        return app($class);
    }

    public static function availableProviders(): array
    {
        return [
            'digitalocean' => 'DigitalOcean',
            'aws' => 'Amazon Web Services (AWS)',
            'hetzner' => 'Hetzner',
        ];
    }
}
