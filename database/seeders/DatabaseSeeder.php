<?php

namespace Database\Seeders;

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //
        // verityDeploy starts clean by default, but we keep one real login
        // account and one starter server record so the first run is easy.
        //
        $user = User::query()->updateOrCreate([
            'email' => 'kmoses@monaksoft.com',
        ], [
            'name' => 'Kufre Moses',
            'password' => 'password',
            'email_verified_at' => now(),
            'alert_inbox_enabled' => true,
            'alert_email_enabled' => true,
            'alert_minimum_level' => 'warning',
        ]);

        $team = $user->ownedTeams()->first()
            ?? $user->teams()->first()
            ?? Team::query()->create([
                'owner_id' => $user->id,
                'name' => "{$user->name}'s Team",
                'slug' => str($user->email)->before('@')->slug()->append('-team')->toString(),
                'description' => 'Personal workspace for the first live server.',
            ]);

        if (! $user->teams()->whereKey($team->id)->exists()) {
            $user->teams()->attach($team->id, [
                'role' => 'owner',
            ]);
        }

        Server::query()->updateOrCreate([
            'host' => 'freshfromnaija.com',
        ], [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => 'freshfromnaija.com',
            'ip_address' => 'freshfromnaija.com',
            'port' => 22,
            'ssh_port' => 22,
            'username' => 'fresufea',
            'ssh_user' => 'fresufea',
            'provider_type' => 'cpanel',
            'provider_reference' => 'freshfromnaija.com',
            'provider_region' => 'production',
            'provider_metadata' => [
                'account' => 'fresufea',
                'bootstrap' => 'pending',
            ],
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'status' => 'pending',
            'notes' => 'Starter cPanel server. Add the real API token in the Server form, then run the cPanel wizard to discover the SSH port, validate the API, and bootstrap the workspace.',
        ]);
    }
}
