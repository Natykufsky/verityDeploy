<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelDomainProvisioner;
use App\Services\Cpanel\CpanelSiteProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CpanelDomainProvisionerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_links_a_site_domain_to_the_current_public_entry_point(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'can_manage_domains' => true,
            'status' => 'online',
            'cpanel_credential_profile_id' => $this->cpanelProfile()->id,
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'VerityAPI',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/verityapi.git',
            'default_branch' => 'main',
            'web_root' => 'public',
        ]);

        $primaryDomain = Domain::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => 'verityapi.monaksoft.com.ng',
            'type' => 'primary',
            'is_active' => true,
        ]);

        $site->forceFill([
            'primary_domain_id' => $primaryDomain->id,
        ])->save();
        $site = $site->fresh(['server', 'primaryDomain']);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('ping')->once()->andReturn([]);
        $client->shouldReceive('addAddonDomain')
            ->once()
            ->with(
                Mockery::any(),
                'verityapi.monaksoft.com.ng',
                'verityapi_monaksoft_com_ng',
                'public_html/verityapi.monaksoft.com.ng/current/public',
            )
            ->andReturn([]);
        $client->shouldReceive('addSubdomain')->never();
        $client->shouldReceive('parkDomain')->never();
        $client->shouldReceive('toHomeRelativePath')
            ->andReturnUsing(function ($serverArg, string $path): string {
                $home = '/home/monaksof';
                $path = str_replace('\\', '/', trim($path));

                if (str_starts_with($path, $home.'/')) {
                    return ltrim(substr($path, strlen($home) + 1), '/');
                }

                if (str_starts_with($path, $home)) {
                    return ltrim(substr($path, strlen($home)), '/');
                }

                return ltrim($path, '/');
            });

        $siteProvisioner = Mockery::mock(CpanelSiteProvisioner::class);
        $siteProvisioner->shouldReceive('ensureWorkspace')
            ->once()
            ->with(Mockery::on(fn (Site $receivedSite): bool => $receivedSite->is($site)))
            ->andReturnNull();

        $provisioner = new CpanelDomainProvisioner($client, $siteProvisioner);

        $summary = $provisioner->provision($site);

        $this->assertContains('Created the addon domain verityapi.monaksoft.com.ng.', $summary);
    }

    protected function cpanelProfile(): CredentialProfile
    {
        return CredentialProfile::query()->create([
            'name' => 'CPanel Profile',
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
