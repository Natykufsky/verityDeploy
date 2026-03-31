<?php

namespace App\Services\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    /**
     * @return array{status:string, user?:User, invitation?:TeamInvitation, token?:string}
     */
    public function invite(Team $team, User $inviter, array $data): array
    {
        if (! $inviter->canManageTeam($team)) {
            throw new AuthorizationException('You are not allowed to invite members to this team.');
        }

        $email = Str::of($data['email'])->trim()->lower()->toString();
        $role = $data['role'] ?? 'member';
        $name = filled($data['name'] ?? null) ? trim((string) $data['name']) : null;
        $message = filled($data['message'] ?? null) ? trim((string) $data['message']) : null;

        $existingUser = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            $team->members()->syncWithoutDetaching([
                $existingUser->id => ['role' => $role],
            ]);
            $team->members()->updateExistingPivot($existingUser->id, [
                'role' => $role,
            ]);

            return [
                'status' => 'attached',
                'user' => $existingUser,
            ];
        }

        $pendingInvitation = TeamInvitation::query()
            ->pending()
            ->where('team_id', $team->id)
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        $token = Str::random(64);

        if ($pendingInvitation) {
            $pendingInvitation->update([
                'name' => $name,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by_user_id' => $inviter->id,
                'message' => $message,
                'expires_at' => now()->addDays(7),
            ]);

            $invitation = $pendingInvitation;
        } else {
            $invitation = TeamInvitation::query()->create([
                'team_id' => $team->id,
                'email' => $email,
                'name' => $name,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by_user_id' => $inviter->id,
                'message' => $message,
                'expires_at' => now()->addDays(7),
            ]);
        }

        Notification::route('mail', $email)->notify(new TeamInvitationNotification(
            $invitation->fresh(['team', 'invitedBy']),
            $token,
            $inviter,
        ));

        return [
            'status' => 'invited',
            'invitation' => $invitation,
            'token' => $token,
        ];
    }

    /**
     * @return array{status:string, invitation:TeamInvitation, token:string}
     */
    public function resend(TeamInvitation $invitation, User $sender): array
    {
        if (! $sender->canManageTeam($invitation->team)) {
            throw new AuthorizationException('You are not allowed to manage invitations for this team.');
        }

        if (! $invitation->accepted_at) {
            $token = Str::random(64);

            $invitation->update([
                'token_hash' => hash('sha256', $token),
                'invited_by_user_id' => $sender->id,
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $invitation->email)->notify(new TeamInvitationNotification(
                $invitation->fresh(['team', 'invitedBy']),
                $token,
                $sender,
            ));

            return [
                'status' => 'resent',
                'invitation' => $invitation->fresh(),
                'token' => $token,
            ];
        }

        throw ValidationException::withMessages([
            'invitation' => 'This invitation has already been accepted.',
        ]);
    }

    public function updateMemberRole(Team $team, User $actor, User $member, string $role): void
    {
        if (! $actor->canManageTeam($team)) {
            throw new AuthorizationException('You are not allowed to manage members for this team.');
        }

        if ($team->owner_id === $member->id) {
            throw ValidationException::withMessages([
                'role' => 'The team owner role cannot be changed here.',
            ]);
        }

        if (! in_array($role, ['admin', 'member', 'viewer'], true)) {
            throw ValidationException::withMessages([
                'role' => 'The selected role is not valid.',
            ]);
        }

        $team->members()->updateExistingPivot($member->id, [
            'role' => $role,
        ]);
    }

    public function accept(string $token, array $data): User
    {
        $invitation = TeamInvitation::query()
            ->with(['team', 'invitedBy'])
            ->pending()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'token' => 'This team invitation is no longer valid.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'This team invitation has expired.',
            ]);
        }

        $email = Str::of($invitation->email)->trim()->lower()->toString();

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $user) {
            $name = filled($data['name'] ?? null) ? trim((string) $data['name']) : $invitation->name;

            $validated = validator(array_merge($data, [
                'name' => $name,
                'email' => $email,
            ]), [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ])->validate();

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
            ]);
        }

        if ($user->email !== $email) {
            throw ValidationException::withMessages([
                'email' => 'The invitation email does not match the account that is being used.',
            ]);
        }

        $invitation->team->members()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role],
        ]);
        $invitation->team->members()->updateExistingPivot($user->id, [
            'role' => $invitation->role,
        ]);

        $invitation->update([
            'accepted_user_id' => $user->id,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    public function findByToken(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->with(['team', 'invitedBy'])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}
