<?php

namespace Tests\Unit;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Services\Teams\TeamInvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamInvitationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_attaches_existing_users_immediately(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $member = User::query()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = app(TeamInvitationService::class)->invite($team, $owner, [
            'email' => $member->email,
            'name' => $member->name,
            'role' => 'admin',
        ]);

        $this->assertSame('attached', $result['status']);
        $this->assertDatabaseMissing('team_invitations', [
            'team_id' => $team->id,
            'email' => $member->email,
        ]);
        $this->assertSame('admin', $member->fresh()->teamMembershipRole($team));
    }

    public function test_it_creates_and_emails_invites_for_new_addresses(): void
    {
        Notification::fake();

        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $result = app(TeamInvitationService::class)->invite($team, $owner, [
            'email' => 'newmember@example.com',
            'name' => 'New Member',
            'role' => 'viewer',
            'message' => 'Welcome aboard.',
        ]);

        $this->assertSame('invited', $result['status']);
        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'email' => 'newmember@example.com',
            'role' => 'viewer',
        ]);

        Notification::assertSentOnDemand(TeamInvitationNotification::class, function (TeamInvitationNotification $notification, array $channels, object $notifiable): bool {
            return in_array('mail', $channels, true)
                && $notification->invitation->email === 'newmember@example.com';
        });
    }

    public function test_it_lets_new_users_accept_an_invitation_and_join_the_team(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $service = app(TeamInvitationService::class);
        $result = $service->invite($team, $owner, [
            'email' => 'futuremember@example.com',
            'name' => 'Future Member',
            'role' => 'member',
        ]);

        $this->assertSame('invited', $result['status']);

        $invite = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('email', 'futuremember@example.com')
            ->firstOrFail();

        $user = $service->accept($result['token'], [
            'name' => 'Future Member',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'futuremember@example.com',
        ]);
        $this->assertSame('member', $user->teamMembershipRole($team));
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_it_can_resend_pending_invites_and_rotate_the_link(): void
    {
        Notification::fake();

        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $service = app(TeamInvitationService::class);
        $created = $service->invite($team, $owner, [
            'email' => 'resend@example.com',
            'name' => 'Resend Person',
            'role' => 'viewer',
        ]);

        $invitation = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('email', 'resend@example.com')
            ->firstOrFail();

        $resent = $service->resend($invitation, $owner);

        $this->assertSame('resent', $resent['status']);
        $this->assertNotSame($created['token'], $resent['token']);

        Notification::assertSentOnDemand(TeamInvitationNotification::class, function (TeamInvitationNotification $notification, array $channels, object $notifiable): bool {
            return in_array('mail', $channels, true)
                && $notification->invitation->email === 'resend@example.com';
        });
    }

    public function test_it_can_update_member_roles(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $member = User::query()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        $team->members()->attach($member->id, [
            'role' => 'member',
        ]);

        app(TeamInvitationService::class)->updateMemberRole($team, $owner, $member, 'admin');

        $this->assertSame('admin', $member->fresh()->teamMembershipRole($team));
    }

    public function test_it_rejects_owner_role_changes(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = $owner->teams()->firstOrFail();

        $this->expectException(ValidationException::class);

        app(TeamInvitationService::class)->updateMemberRole($team, $owner, $owner, 'member');
    }
}
