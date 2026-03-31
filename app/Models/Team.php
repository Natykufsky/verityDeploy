<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->pending();
    }

    public function invitationOptions(): array
    {
        return $this->pendingInvitations()
            ->latest()
            ->get()
            ->mapWithKeys(fn (TeamInvitation $invitation): array => [
                $invitation->id => sprintf('%s (%s)', $invitation->email, $invitation->role),
            ])
            ->all();
    }

    public function pendingInvitationsCount(): int
    {
        return $this->pendingInvitations()->count();
    }

    public function nextPendingInvitationExpiry(): ?TeamInvitation
    {
        return $this->pendingInvitations()
            ->orderBy('expires_at')
            ->first();
    }

    public function pendingInvitationExpiryLabel(): string
    {
        $nextExpiry = $this->nextPendingInvitationExpiry();

        if (! $nextExpiry?->expires_at) {
            return 'No pending invites';
        }

        return sprintf('%s expires %s', $nextExpiry->email, $nextExpiry->expires_at->diffForHumans());
    }

    public function memberRoleLabel(?string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Member',
            'viewer' => 'Viewer',
            default => 'Member',
        };
    }

    public function memberRoleBadgeColor(?string $role): string
    {
        return match ($role) {
            'owner' => 'warning',
            'admin' => 'primary',
            'member' => 'success',
            'viewer' => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return Builder<self>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $query): Builder => $query->whereKey($user->id));
        });
    }
}
