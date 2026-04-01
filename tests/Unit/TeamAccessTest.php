<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_users_get_a_personal_team_and_can_share_access(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();
        $this->assertSame('owner', $owner->teamMembershipRole($team));
        $this->assertTrue($owner->canManageTeam($team));

        $member = User::query()->create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        $team->members()->attach($member->id, [
            'role' => 'member',
        ]);

        $server = Server::query()->create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'name' => 'Shared Server',
            'ip_address' => '203.0.113.50',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'team_id' => $team->id,
            'name' => 'shared-site',
            'deploy_path' => '/var/www/shared-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/shared-site.git',
            'default_branch' => 'main',
        ]);

        $this->assertTrue($member->canAccessTeam($team));
        $this->assertTrue($member->canAccessServer($server));
        $this->assertTrue($member->canAccessSite($site));
    }
}
