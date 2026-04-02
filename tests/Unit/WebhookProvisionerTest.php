<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Site;
use App\Services\GitHub\WebhookProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookProvisionerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_provision_creates_or_updates_a_github_webhook_record(): void
    {
        $this->seedAppSettings();

        $server = Server::query()->create([
            'name' => 'Webhook Server',
            'ip_address' => '203.0.113.90',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'online',
        ]);

        $webhookCredentialProfile = CredentialProfile::query()->create([
            'name' => 'Webhook Secret Profile',
            'type' => 'webhook',
            'is_active' => true,
            'settings' => ['webhook_secret' => 'shared-secret'],
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'github-site',
            'repository_url' => 'https://github.com/acme/github-site.git',
            'default_branch' => 'main',
            'deploy_path' => '/var/www/github-site',
            'deploy_source' => 'git',
            'webhook_credential_profile_id' => $webhookCredentialProfile->id,
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/github-site/hooks' => Http::response([
                'id' => 456,
                'config' => [
                    'url' => 'http://localhost/webhooks/github',
                ],
            ], 201),
        ]);

        $result = app(WebhookProvisioner::class)->provision($site);

        $this->assertSame(456, $result['id']);

        $site = $site->fresh();

        $this->assertSame('456', $site->github_webhook_id);
        $this->assertSame('provisioned', $site->github_webhook_status);
        $this->assertNotNull($site->github_webhook_synced_at);
        $this->assertNotEmpty($site->webhook_secret);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/repos/acme/github-site/hooks');
        });
    }

    public function test_refresh_status_marks_a_missing_webhook_as_needing_sync(): void
    {
        $this->seedAppSettings();

        $server = Server::query()->create([
            'name' => 'Webhook Server',
            'ip_address' => '203.0.113.91',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'missing-hook-site',
            'repository_url' => 'https://github.com/acme/missing-hook-site.git',
            'default_branch' => 'main',
            'deploy_path' => '/var/www/missing-hook-site',
            'deploy_source' => 'git',
            'github_webhook_id' => '123',
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/missing-hook-site/hooks/123' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $result = app(WebhookProvisioner::class)->refreshStatus($site);

        $this->assertSame('needs-sync', $result['status']);
        $this->assertSame('needs-sync', $site->fresh()->github_webhook_status);
        $this->assertSame('Remote GitHub webhook was not found.', $site->fresh()->github_webhook_last_error);
    }

    public function test_remove_deletes_the_remote_webhook_and_clears_local_state(): void
    {
        $this->seedAppSettings();

        $server = Server::query()->create([
            'name' => 'Webhook Server',
            'ip_address' => '203.0.113.92',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'removal-site',
            'repository_url' => 'https://github.com/acme/removal-site.git',
            'default_branch' => 'main',
            'deploy_path' => '/var/www/removal-site',
            'deploy_source' => 'git',
            'github_webhook_id' => '789',
            'github_webhook_status' => 'provisioned',
        ]);

        Http::fake([
            'https://api.github.com/repos/acme/removal-site/hooks/789' => Http::response([], 204),
        ]);

        app(WebhookProvisioner::class)->remove($site);

        $site = $site->fresh();

        $this->assertNull($site->github_webhook_id);
        $this->assertSame('unprovisioned', $site->github_webhook_status);
        $this->assertNull($site->github_webhook_last_error);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/repos/acme/removal-site/hooks/789');
        });
    }

    protected function seedAppSettings(): void
    {
        putenv('GITHUB_API_TOKEN=test-github-token');
        $_ENV['GITHUB_API_TOKEN'] = 'test-github-token';
        $_SERVER['GITHUB_API_TOKEN'] = 'test-github-token';

        AppSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_php_version' => '8.3',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'github_webhook_path' => '/webhooks/github',
            'github_webhook_events' => 'push',
        ]);
    }
}
