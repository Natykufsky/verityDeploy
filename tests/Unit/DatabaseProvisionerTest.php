<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Database;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Databases\DatabaseProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class DatabaseProvisionerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_provisions_a_cpanel_database_with_prefixed_names(): void
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
            'name' => 'Database Site',
            'deploy_path' => '/home/monaksof/public_html/database.example.com',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/database-site.git',
            'default_branch' => 'main',
            'create_database' => true,
            'database_name' => 'Database Prod',
        ]);

        $database = Database::query()->create([
            'site_id' => $site->id,
            'server_id' => $server->id,
            'name' => 'Database Prod',
            'username' => 'database-prod',
            'password' => 'secret-password',
            'status' => 'requested',
            'notes' => 'Provision me',
        ]);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('ping')->once()->andReturn([]);
        $client->shouldReceive('createDatabase')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod')
            ->andReturn([]);
        $client->shouldReceive('createDatabaseUser')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod', 'secret-password')
            ->andReturn([]);
        $client->shouldReceive('setDatabasePrivileges')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod', 'monaksof_database_prod', 'ALL PRIVILEGES')
            ->andReturn([]);

        $service = new DatabaseProvisioner($client);

        $summary = $service->provision($database->fresh(['server', 'site']));

        $this->assertContains('Created database monaksof_database_prod.', $summary);
        $this->assertContains('Created user monaksof_database_prod.', $summary);
        $this->assertSame('provisioned', $database->fresh()->status);
        $this->assertNotNull($database->fresh()->provisioned_at);
    }

    public function test_it_deletes_a_cpanel_database_with_cleanup(): void
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
            'name' => 'Database Site',
            'deploy_path' => '/home/monaksof/public_html/database.example.com',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/database-site.git',
            'default_branch' => 'main',
            'create_database' => true,
            'database_name' => 'Database Prod',
        ]);

        $database = Database::query()->create([
            'site_id' => $site->id,
            'server_id' => $server->id,
            'name' => 'Database Prod',
            'username' => 'database-prod',
            'password' => 'secret-password',
            'status' => 'provisioned',
            'provisioned_at' => now(),
        ]);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('ping')->once()->andReturn([]);
        $client->shouldReceive('revokeDatabasePrivileges')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod', 'monaksof_database_prod')
            ->andReturn([]);
        $client->shouldReceive('deleteDatabaseUser')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod')
            ->andReturn([]);
        $client->shouldReceive('deleteDatabase')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), 'monaksof_database_prod')
            ->andReturn([]);

        $service = new DatabaseProvisioner($client);

        $summary = $service->delete($database->fresh(['server', 'site']));

        $this->assertContains('Deleted database monaksof_database_prod.', $summary);
        $this->assertSame('deleted', $database->fresh()->status);
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
