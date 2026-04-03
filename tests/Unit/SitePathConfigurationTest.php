<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SitePathConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_derives_the_deploy_path_from_the_linked_domain_and_server_account(): void
    {
        $sshProfile = CredentialProfile::query()->create([
            'name' => 'SSH Profile',
            'type' => 'ssh',
            'description' => 'Test SSH profile',
            'settings' => [
                'username' => 'monaksof',
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
            'ssh_credential_profile_id' => $sshProfile->id,
            'status' => 'online',
        ]);

        $path = Site::deriveDeployPathFromDomain($server, 'verityapi.monaksoft.com.ng');

        $this->assertSame('/home/monaksof/public_html/verityapi.monaksoft.com.ng', $path);
    }
}
