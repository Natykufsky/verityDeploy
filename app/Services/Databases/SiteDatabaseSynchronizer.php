<?php

namespace App\Services\Databases;

use App\Models\Database;
use App\Models\Site;
use Illuminate\Support\Str;
use Throwable;

class SiteDatabaseSynchronizer
{
    public function __construct(
        protected DatabaseProvisioner $provisioner,
    ) {}

    public function sync(Site $site): ?Database
    {
        if (! $site->create_database) {
            $database = $site->database;

            if ($database) {
                try {
                    if ($database->status === 'provisioned' && $database->server?->connection_type === 'cpanel') {
                        $this->provisioner->delete($database->fresh(['server', 'site']));
                    }
                } catch (Throwable $throwable) {
                    $database->forceFill([
                        'status' => 'failed',
                        'last_synced_at' => now(),
                        'last_error' => $throwable->getMessage(),
                    ])->save();

                    return $database->fresh();
                }

                $database->delete();
            }

            return null;
        }

        $name = trim((string) $site->database_name);

        if ($name === '') {
            $name = Str::snake((string) $site->name);
        }

        $existingDatabase = $site->database;
        $needsProvisioning = ! $existingDatabase || ! in_array($existingDatabase->status, ['provisioned', 'deleted'], true);

        $database = $site->database()->updateOrCreate(
            [
                'site_id' => $site->id,
            ],
            [
                'server_id' => $site->server_id,
                'name' => $name,
                'username' => $name,
                'password' => $existingDatabase?->password ?: Str::random(24),
                'status' => $existingDatabase?->status ?? 'requested',
                'last_synced_at' => now(),
                'last_error' => null,
                'notes' => 'Database provisioning requested from the site form.',
            ],
        );

        if ($needsProvisioning && $site->server?->connection_type === 'cpanel' && filled($site->server?->cpanel_api_token)) {
            try {
                $this->provisioner->provision($database->fresh(['server', 'site']));
            } catch (Throwable $throwable) {
                $database->forceFill([
                    'status' => 'failed',
                    'last_synced_at' => now(),
                    'last_error' => $throwable->getMessage(),
                ])->save();

                return $database->fresh();
            }
        }

        return $database->fresh();
    }
}
