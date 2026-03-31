<?php

namespace Tests\Unit;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamSummaryHelpersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_returns_the_next_invite_expiry_in_plain_language(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'email' => 'later@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'later'),
            'expires_at' => now()->addDays(3),
        ]);

        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'email' => 'sooner@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'sooner'),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame('sooner@example.com', $team->fresh()->nextPendingInvitationExpiry()?->email);
        $this->assertStringContainsString('sooner@example.com', $team->fresh()->pendingInvitationExpiryLabel());
    }
}
