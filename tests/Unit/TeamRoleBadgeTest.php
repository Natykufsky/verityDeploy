<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamRoleBadgeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_maps_member_roles_to_readable_labels_and_badges(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $this->assertSame('Owner', $team->memberRoleLabel('owner'));
        $this->assertSame('Admin', $team->memberRoleLabel('admin'));
        $this->assertSame('Member', $team->memberRoleLabel('member'));
        $this->assertSame('Viewer', $team->memberRoleLabel('viewer'));

        $this->assertSame('warning', $team->memberRoleBadgeColor('owner'));
        $this->assertSame('primary', $team->memberRoleBadgeColor('admin'));
        $this->assertSame('success', $team->memberRoleBadgeColor('member'));
        $this->assertSame('gray', $team->memberRoleBadgeColor('viewer'));
    }
}
