<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Domains\DomainServerSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainServerSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_a_subdomain_on_cpanel_when_a_domain_is_saved(): void
    {
        $site = $this->createCpanelSite();

        $domain = Domain::query()->create([
            'server_id' => $site->server_id,
            'site_id' => $site->id,
            'name' => 'blog.example.com',
            'type' => 'subdomain',
            'web_root' => '/home/monaksof/public_html/example.com/blog',
            'is_active' => true,
        ]);

        Http::fake([
            '*' => Http::response([
                'cpanelresult' => [
                    'event' => ['result' => 1],
                    'data' => [],
                ],
            ], 200),
        ]);

        $result = app(DomainServerSyncService::class)->syncCreated($domain);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['synced']);
        $this->assertStringContainsString('Created subdomain blog.example.com', $result['message']);

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'cpanel_jsonapi_func=addsubdomain'));
    }

    public function test_it_deletes_an_alias_from_cpanel_when_the_domain_is_removed(): void
    {
        $site = $this->createCpanelSite();

        $domain = Domain::query()->create([
            'server_id' => $site->server_id,
            'site_id' => $site->id,
            'name' => 'alias.example.com',
            'type' => 'alias',
            'web_root' => '/home/monaksof/public_html/example.com',
            'is_active' => true,
        ]);

        Http::fake([
            '*' => Http::response([
                'cpanelresult' => [
                    'event' => ['result' => 1],
                    'data' => [],
                ],
            ], 200),
        ]);

        $result = app(DomainServerSyncService::class)->syncDeleted($domain);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['synced']);
        $this->assertStringContainsString('Removed alias domain alias.example.com', $result['message']);

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'cpanel_jsonapi_func=unpark'));
    }

    protected function createCpanelSite(): Site
    {
        $profile = CredentialProfile::query()->create([
            'name' => 'cPanel Profile',
            'type' => 'cpanel',
            'description' => 'Test cPanel profile',
            'settings' => [
                'username' => 'monaksof',
                'api_token' => 'test-token',
                'api_port' => 2083,
            ],
            'is_default' => false,
            'is_active' => true,
        ]);

        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'cpanel_credential_profile_id' => $profile->id,
            'can_manage_domains' => true,
            'status' => 'online',
        ]);

        $primaryDomain = Domain::query()->create([
            'server_id' => $server->id,
            'name' => 'example.com',
            'type' => 'primary',
            'web_root' => '/home/monaksof/public_html/example.com',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'primary_domain_id' => $primaryDomain->id,
            'name' => 'Example Site',
            'deploy_path' => '/home/monaksof/public_html/example.com',
            'deploy_source' => 'local',
            'web_root' => 'public',
            'force_https' => true,
            'ssl_state' => 'valid',
        ]);

        return $site;
    }
}
