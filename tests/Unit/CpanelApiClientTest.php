<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Services\Cpanel\CpanelApiClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CpanelApiClientTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authorize_ssh_key_uses_importkey_and_authkey(): void
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
            'name' => 'cPanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'cpanel_credential_profile_id' => $profile->id,
            'status' => 'online',
        ]);

        Http::fake([
            '*' => Http::response([
                'cpanelresult' => [
                    'event' => ['result' => 1],
                    'data' => [],
                ],
            ], 200),
        ]);

        app(CpanelApiClient::class)->authorizeSshKey($server, 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQTest test@example.com');

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_contains($request->body(), 'cpanel_jsonapi_func=importkey')
                && str_contains($request->body(), 'cpanel_jsonapi_module=SSH')
                && str_contains($request->body(), 'cpanel_jsonapi_user=monaksof')
                && str_contains($request->body(), 'key=ssh-rsa+AAAAB3NzaC1yc2EAAAADAQABAAABAQTest+test%40example.com');
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->body(), 'cpanel_jsonapi_func=authkey')
                && str_contains($request->body(), 'action=authorize')
                && str_contains($request->body(), 'key=veritydeploy-dashboard');
        });
    }

    public function test_upload_file_uses_uapi_upload_files(): void
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
            'name' => 'cPanel Server',
            'ip_address' => 'monaksoft.com',
            'provider_reference' => '147.124.214.12',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'cpanel_credential_profile_id' => $profile->id,
            'status' => 'online',
        ]);

        $tmp = storage_path('app/test-upload.txt');
        file_put_contents($tmp, 'hello world');

        Http::fake([
            '*' => Http::response([
                'status' => 1,
                'result' => [
                    'status' => 1,
                    'data' => [],
                ],
            ], 200),
        ]);

        try {
            app(CpanelApiClient::class)->uploadFile($server, '/tmp', $tmp, 'test-upload.txt');
        } finally {
            @unlink($tmp);
        }

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), '/execute/Fileman/upload_files')
                && str_contains($request->body(), 'name="dir"')
                && str_contains($request->body(), "\r\n\r\ntmp\r\n")
                && str_contains($request->body(), 'name="file-1"')
                && str_contains($request->body(), 'filename="test-upload.txt"');
        });
    }

    public function test_database_provisioning_methods_use_mysql_uapi_endpoints(): void
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
            'status' => 'online',
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => 1,
                'result' => [
                    'status' => 1,
                    'data' => [],
                ],
            ], 200),
        ]);

        $client = app(CpanelApiClient::class);
        $client->createDatabase($server, 'monaksof_database_prod');
        $client->createDatabaseUser($server, 'monaksof_database_prod', 'secret-password');
        $client->setDatabasePrivileges($server, 'monaksof_database_prod', 'monaksof_database_prod');
        $client->revokeDatabasePrivileges($server, 'monaksof_database_prod', 'monaksof_database_prod');
        $client->deleteDatabaseUser($server, 'monaksof_database_prod');
        $client->deleteDatabase($server, 'monaksof_database_prod');

        Http::assertSentCount(6);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/create_database'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/create_user'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/set_privileges_on_database'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/revoke_access_to_database'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/delete_user'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/execute/Mysql/delete_database'));
    }
}
