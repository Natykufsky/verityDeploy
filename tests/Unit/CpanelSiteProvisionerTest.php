<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelSiteProvisioner;
use App\Services\Deployment\ReleaseManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CpanelSiteProvisionerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_shared_runtime_creates_symlinks_via_cpanel_api(): void
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
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/verityapi.git',
            'default_branch' => 'main',
        ]);

        $releaseManager = Mockery::mock(ReleaseManager::class);
        $releaseManager->shouldReceive('environmentFileContents')
            ->once()
            ->with(Mockery::on(fn (Site $receivedSite): bool => $receivedSite->is($site)))
            ->andReturn('APP_ENV=production');
        $releaseManager->shouldReceive('sharedFiles')
            ->with(Mockery::on(fn (Site $receivedSite): bool => $receivedSite->is($site)))
            ->andReturn([]);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('mkdir')
            ->andReturn(['status' => 1]);
        $client->shouldReceive('saveFile')
            ->once()
            ->with(
                Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)),
                '/home/monaksof/public_html/verityapi.monaksoft.com.ng/shared',
                '.env',
                'APP_ENV=production',
            )
            ->andReturn([]);
        $client->shouldReceive('linkPath')
            ->twice()
            ->withArgs(function (Server $receivedServer, string $sourcePath, string $destinationPath) use ($server): bool {
                return $receivedServer->is($server)
                    && str_contains($sourcePath, '/shared/')
                    && str_contains($destinationPath, '/releases/20260403013723-425/');
            })
            ->andReturn([]);

        $provisioner = new CpanelSiteProvisioner($client, $releaseManager);

        $summary = $provisioner->syncSharedRuntime($site, '/home/monaksof/public_html/verityapi.monaksoft.com.ng/releases/20260403013723-425');

        $this->assertSame('Updated shared .env.', $summary[0]);
        $this->assertSame('Linked shared .env and storage into the active release.', $summary[1]);
    }

    public function test_activate_release_updates_the_live_cpanel_docroot(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'status' => 'online',
        ]);

        $currentDomain = Domain::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'primary_domain_id' => $currentDomain->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/verityapi.git',
            'default_branch' => 'main',
            'web_root' => 'public',
        ]);

        $releasePath = '/home/monaksof/public_html/verityapi.monaksoft.com.ng/releases/20260403031408-653';

        $releaseManager = Mockery::mock(ReleaseManager::class);
        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('changeSubdomainDocroot')
            ->never();
        $client->shouldReceive('mkdir')->andReturn(['status' => 1]);
        $client->shouldReceive('saveFile')->andReturn([]);
        $client->shouldReceive('linkPath')
            ->once()
            ->with(
                Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)),
                $releasePath,
                '/home/monaksof/public_html/verityapi.monaksoft.com.ng/current',
            )
            ->andReturn([]);

        $provisioner = new CpanelSiteProvisioner($client, $releaseManager);

        $summary = $provisioner->activateRelease($site, $releasePath);

        $this->assertSame('Activated release '.$releasePath.'.', $summary[0]);
        $this->assertSame($releasePath, $site->fresh()->current_release_path);
    }
}
