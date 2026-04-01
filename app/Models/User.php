<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'alert_inbox_enabled',
        'alert_email_enabled',
        'alert_minimum_level',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'alert_inbox_enabled' => 'boolean',
            'alert_email_enabled' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->teams()->exists()) {
                return;
            }

            $team = Team::query()->create([
                'owner_id' => $user->id,
                'name' => "{$user->name}'s Team",
                'slug' => str($user->email)->before('@')->slug()->append('-team')->toString(),
                'description' => 'Personal workspace created automatically for the account owner.',
            ]);

            $user->teams()->attach($team->id, [
                'role' => 'owner',
            ]);
        });
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'triggered_by_user_id');
    }

    public function alertInboxEnabled(): bool
    {
        return (bool) $this->alert_inbox_enabled;
    }

    public function alertEmailEnabled(): bool
    {
        return (bool) $this->alert_email_enabled;
    }

    public function alertMinimumLevel(): string
    {
        return (string) ($this->alert_minimum_level ?: 'warning');
    }

    public function teamMembershipRole(Team|int|null $team): ?string
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        if (! $teamId) {
            return null;
        }

        if ($this->ownedTeams()->whereKey($teamId)->exists()) {
            return 'owner';
        }

        return $this->teams()
            ->whereKey($teamId)
            ->first()
            ?->pivot
            ?->role;
    }

    public function canAccessTeam(Team|int|null $team): bool
    {
        $role = $this->teamMembershipRole($team);

        return in_array($role, ['owner', 'admin', 'member', 'viewer'], true);
    }

    public function canManageTeam(Team|int|null $team): bool
    {
        $role = $this->teamMembershipRole($team);

        return in_array($role, ['owner', 'admin'], true);
    }

    public function canAccessServer(Server $server): bool
    {
        return $server->user_id === $this->id
            || filled($server->team_id) && $this->canAccessTeam($server->team_id);
    }

    public function canManageServer(Server $server): bool
    {
        return $server->user_id === $this->id
            || filled($server->team_id) && $this->canManageTeam($server->team_id);
    }

    public function canAccessSite(Site $site): bool
    {
        return $site->server?->user_id === $this->id
            || filled($site->team_id) && $this->canAccessTeam($site->team_id)
            || filled($site->server?->team_id) && $this->canAccessTeam($site->server->team_id);
    }

    public function canManageSite(Site $site): bool
    {
        return $site->server?->user_id === $this->id
            || filled($site->team_id) && $this->canManageTeam($site->team_id)
            || filled($site->server?->team_id) && $this->canManageTeam($site->server->team_id);
    }
}
