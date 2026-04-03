<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Servers\ServerDomainSynchronizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class ServerDomainSynchronizerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_flattens_grouped_cpanel_domain_payloads_into_domain_records(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/example.com',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/verityapi.git',
            'default_branch' => 'main',
        ]);

        $primaryDomain = Domain::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => 'example.com',
            'type' => 'primary',
            'is_active' => true,
        ]);

        $site->forceFill([
            'primary_domain_id' => $primaryDomain->id,
        ])->save();

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('request')
            ->once()
            ->with(
                Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)),
                'DomainInfo',
                'domains_data'
            )
            ->andReturn([
                'main_domain' => [
                    'domain' => 'example.com',
                    'documentroot' => '/home/monaksof/public_html/example.com/public',
                    'type' => 'main',
                ],
                'addon_domains' => [
                    [
                        'domain' => 'blog.example.net',
                        'documentroot' => '/home/monaksof/public_html/blog',
                        'type' => 'addon',
                        'php_version' => '8.2',
                    ],
                ],
                'sub_domains' => [
                    [
                        'domain' => 'www.example.com',
                        'documentroot' => '/home/monaksof/public_html/www',
                        'type' => 'sub',
                    ],
                ],
                'parked_domains' => [
                    [
                        'domain' => 'alias.example.com',
                        'documentroot' => '/home/monaksof/public_html/alias',
                        'type' => 'parked',
                    ],
                ],
            ]);

        $synchronizer = new ServerDomainSynchronizer($client);
        $result = $synchronizer->sync($server);

        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['count']);
        $this->assertSame('Successfully synced 4 domains with cPanel metadata.', $result['message']);

        $this->assertDatabaseHas('domains', [
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => 'example.com',
            'type' => 'primary',
        ]);

        $this->assertDatabaseHas('domains', [
            'server_id' => $server->id,
            'name' => 'blog.example.net',
            'type' => 'addon',
        ]);

        $primaryDomain = Domain::query()
            ->where('server_id', $server->id)
            ->where('name', 'example.com')
            ->first();

        $this->assertNotNull($primaryDomain);
        $this->assertSame('/home/monaksof/public_html/example.com/public', $primaryDomain->web_root);
        $this->assertSame('primary', $primaryDomain->type);
    }
}
