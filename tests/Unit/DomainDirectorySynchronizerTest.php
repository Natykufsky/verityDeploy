<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Domains\DomainDirectorySynchronizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainDirectorySynchronizerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_pushes_subdomain_document_root_updates_to_cpanel(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'cpanel_credential_profile_id' => $this->cpanelProfile()->id,
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

        $domain = Domain::query()->create([
            'server_id' => $server->id,
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

        $result = app(DomainDirectorySynchronizer::class)->sync($domain);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['synced']);
        $this->assertStringContainsString('Updated the live cPanel document root', $result['message']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->body(), 'cpanel_jsonapi_func=changedocroot')
                && str_contains($request->body(), 'subdomain=blog.example.com')
                && str_contains($request->body(), 'rootdomain=example.com');
        });
    }

    protected function cpanelProfile(): CredentialProfile
    {
        return CredentialProfile::query()->create([
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
    }
}
