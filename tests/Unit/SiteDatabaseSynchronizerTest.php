<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Database;
use App\Models\Server;
use App\Models\Site;
use App\Services\Databases\DatabaseProvisioner;
use App\Services\Databases\SiteDatabaseSynchronizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class SiteDatabaseSynchronizerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_creates_a_requested_database_record_for_the_site(): void
    {
        $server = Server::query()->create([
            'name' => 'Database Server',
            'ip_address' => '203.0.113.254',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'db-site',
            'deploy_path' => '/var/www/db-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/db-site.git',
            'default_branch' => 'main',
            'create_database' => true,
            'database_name' => 'db_site_prod',
        ]);

        $database = app(SiteDatabaseSynchronizer::class)->sync($site);

        $this->assertInstanceOf(Database::class, $database);
        $this->assertSame($site->id, $database->site_id);
        $this->assertSame($server->id, $database->server_id);
        $this->assertSame('db_site_prod', $database->name);
        $this->assertSame('db_site_prod', $database->username);
        $this->assertSame('requested', $database->status);
        $this->assertNotNull($database->last_synced_at);
        $this->assertNotEmpty($database->password);
    }

    public function test_sync_removes_the_database_record_when_not_requested(): void
    {
        $server = Server::query()->create([
            'name' => 'Database Server',
            'ip_address' => '203.0.113.255',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'db-site',
            'deploy_path' => '/var/www/db-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/db-site.git',
            'default_branch' => 'main',
            'create_database' => true,
            'database_name' => 'db_site_prod',
        ]);

        app(SiteDatabaseSynchronizer::class)->sync($site);

        $site->update([
            'create_database' => false,
        ]);

        app(SiteDatabaseSynchronizer::class)->sync($site->fresh());

        $this->assertDatabaseMissing('databases', [
            'site_id' => $site->id,
        ]);
    }

    public function test_sync_calls_the_provisioner_for_cpanel_sites(): void
    {
        $profile = CredentialProfile::query()->create([
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

        $server = Server::query()->create([
            'name' => 'Database Server',
            'ip_address' => '203.0.113.253',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'cpanel_credential_profile_id' => $profile->id,
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'db-site',
            'deploy_path' => '/var/www/db-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/db-site.git',
            'default_branch' => 'main',
            'create_database' => true,
            'database_name' => 'db_site_prod',
        ]);

        $provisioner = Mockery::mock(DatabaseProvisioner::class);
        $provisioner->shouldReceive('provision')
            ->once()
            ->with(Mockery::on(fn (Database $database): bool => $database->site_id === $site->id))
            ->andReturn(['Provisioned']);

        $synchronizer = new SiteDatabaseSynchronizer($provisioner);

        $database = $synchronizer->sync($site);

        $this->assertInstanceOf(Database::class, $database);
        $this->assertSame('db_site_prod', $database->name);
        $this->assertSame('requested', $database->status);
    }
}
