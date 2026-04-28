<?php

namespace App\Services\Databases;

use App\Models\Database;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteDatabaseSynchronizer
{
    public function sync(Site $site): ?Database
    {
        if (! $site->create_database) {
            $site->database()?->delete();

            return null;
        }

        $name = trim((string) $site->database_name);

        if ($name === '') {
            $name = Str::snake((string) $site->name);
        }

        $database = $site->database()->updateOrCreate(
            [
                'site_id' => $site->id,
            ],
            [
                'server_id' => $site->server_id,
                'name' => $name,
                'username' => $name,
                'status' => 'requested',
                'last_synced_at' => now(),
                'last_error' => null,
                'notes' => 'Database provisioning requested from the site form.',
            ],
        );

        return $database->fresh();
    }
}
