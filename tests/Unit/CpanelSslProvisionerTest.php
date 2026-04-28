<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelSslProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CpanelSslProvisionerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refresh_status_triggers_autossl_and_updates_the_site_timestamp(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'can_manage_ssl' => true,
            'status' => 'online',
        ]);

        $domain = Domain::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'primary_domain_id' => $domain->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'ssl_state' => 'valid',
            'force_https' => true,
        ]);

        $site->setRelation('primary_domain', $domain->name);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('startAutoSslCheck')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)))
            ->andReturn(['status' => 1]);

        $service = new CpanelSslProvisioner($client);

        $summary = $service->refreshStatus($site);

        $this->assertSame('Triggered an AutoSSL check for verityapi.monaksoft.com.ng.', $summary[0]);
        $this->assertSame('The SSL status will update after cPanel finishes the check.', $summary[1]);
        $this->assertNotNull($site->fresh()->ssl_last_synced_at);
        $this->assertNull($site->fresh()->ssl_last_error);
    }

    public function test_sync_https_redirect_applies_the_sites_current_toggle(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'can_manage_ssl' => true,
            'status' => 'online',
        ]);

        $domain = Domain::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'primary_domain_id' => $domain->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'ssl_state' => 'valid',
            'force_https' => true,
        ]);

        $site->setRelation('primary_domain', $domain->name);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('setHttpsRedirect')
            ->once()
            ->with(
                Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)),
                'verityapi.monaksoft.com.ng',
                true,
            )
            ->andReturn(['status' => 1]);

        $service = new CpanelSslProvisioner($client);

        $summary = $service->syncHttpsRedirect($site);

        $this->assertSame('Enabled HTTPS redirects for verityapi.monaksoft.com.ng.', $summary[0]);
        $this->assertSame('The cPanel redirect now matches the site force_https setting.', $summary[1]);
        $this->assertNotNull($site->fresh()->ssl_last_synced_at);
        $this->assertNull($site->fresh()->ssl_last_error);
    }
}
