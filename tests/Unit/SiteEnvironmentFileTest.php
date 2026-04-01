<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Services\Deployment\ReleaseManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SiteEnvironmentFileTest extends TestCase
{
    use DatabaseTransactions;

    protected function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    public function test_it_prefers_a_custom_shared_env_override(): void
    {
        $server = Server::query()->create([
            'name' => 'Env Server',
            'ip_address' => '203.0.113.90',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'env-site',
            'deploy_path' => '/var/www/env-site',
            'deploy_source' => 'git',
            'shared_env_contents' => "APP_NAME=Env Site\nAPP_DEBUG=false\n",
            'environment_variables' => [
                'APP_NAME' => 'Generated Site',
                'APP_DEBUG' => 'true',
            ],
        ]);

        $this->assertSame(
            "APP_NAME=Env Site\nAPP_DEBUG=false",
            $this->normalizeLineEndings(rtrim((string) $site->fresh()->shared_env_contents)),
        );
        $this->assertSame(
            "APP_NAME=Env Site\nAPP_DEBUG=false",
            $this->normalizeLineEndings(rtrim(app(ReleaseManager::class)->environmentFileContents($site->fresh()))),
        );
        $this->assertSame('custom', $site->fresh()->shared_env_mode);
    }

    public function test_it_generates_env_contents_from_key_value_pairs_when_no_override_exists(): void
    {
        $server = Server::query()->create([
            'name' => 'Generated Env Server',
            'ip_address' => '203.0.113.91',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'generated-env-site',
            'deploy_path' => '/var/www/generated-env-site',
            'deploy_source' => 'git',
            'shared_env_contents' => null,
            'environment_variables' => [
                'APP_NAME' => 'Generated Site',
                'APP_DEBUG' => 'false',
            ],
        ]);

        $this->assertSame(
            "APP_NAME=\"Generated Site\"\nAPP_DEBUG=false\n",
            $this->normalizeLineEndings(app(ReleaseManager::class)->environmentFileContents($site->fresh())),
        );
        $this->assertSame('generated', $site->fresh()->shared_env_mode);
    }
}
