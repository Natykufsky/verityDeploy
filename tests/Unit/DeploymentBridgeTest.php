<?php

namespace Tests\Unit;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Services\Deployment\DeploymentBridgeAuth;
use App\Services\Deployment\DeploymentBridgeUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeploymentBridgeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bridge_url_uses_the_configured_websocket_endpoint(): void
    {
        config([
            'deployment.bridge.enabled' => true,
            'deployment.bridge.host' => '127.0.0.1',
            'deployment.bridge.port' => 8789,
            'deployment.bridge.scheme' => 'ws',
        ]);

        $server = Server::query()->create([
            'name' => 'Deploy Bridge Server',
            'ip_address' => '203.0.113.99',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'bridge-site',
            'deploy_path' => '/var/www/bridge-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/bridge-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'running',
            'branch' => 'main',
            'release_path' => '/var/www/bridge-site/releases/20260403000000-1',
        ]);

        $bridge = app(DeploymentBridgeUrl::class)->make($deployment);

        $this->assertTrue($bridge['enabled']);
        $this->assertStringContainsString('ws://127.0.0.1:8789', $bridge['url']);
        $this->assertStringContainsString('deployment_id='.$deployment->id, $bridge['url']);
        $this->assertNotEmpty($bridge['token']);
    }

    public function test_bridge_auth_token_is_stable_for_a_deployment_record(): void
    {
        $server = Server::query()->create([
            'name' => 'Deploy Bridge Server',
            'ip_address' => '203.0.113.100',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'bridge-site',
            'deploy_path' => '/var/www/bridge-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/bridge-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'pending',
            'branch' => 'main',
            'release_path' => '/var/www/bridge-site/releases/20260403000000-2',
        ]);

        $auth = app(DeploymentBridgeAuth::class);

        $token = $auth->token($deployment);

        $this->assertTrue($auth->validate($deployment, $token));
        $this->assertFalse($auth->validate($deployment, $token.'x'));
        $this->assertSame($token, $auth->token($deployment));
    }
}
