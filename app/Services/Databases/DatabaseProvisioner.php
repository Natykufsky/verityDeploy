<?php

namespace App\Services\Databases;

use App\Models\Database;
use App\Services\Cpanel\CpanelApiClient;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseProvisioner
{
    public function __construct(
        protected CpanelApiClient $client,
    ) {}

    /**
     * @return array<int, string>
     */
    public function provision(Database $database): array
    {
        $server = $database->server;

        if (! $server) {
            throw new RuntimeException('The database does not have a server configured.');
        }

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('Database provisioning is only available for cPanel servers.');
        }

        if (! filled($server->effectiveCpanelApiToken())) {
            throw new RuntimeException('The cPanel API token is required before database provisioning can run.');
        }

        $baseName = $this->baseName($database);
        $baseUser = $this->baseUser($database, $baseName);
        $password = $this->password($database);
        $qualifiedDatabase = $this->qualify($server->effectiveCpanelUsername(), $baseName, 64);
        $qualifiedUser = $this->qualify($server->effectiveCpanelUsername(), $baseUser, 32);

        $database->forceFill([
            'name' => $baseName,
            'username' => $baseUser,
            'password' => $password,
            'status' => 'provisioning',
            'last_synced_at' => now(),
            'last_error' => null,
            'notes' => blank($database->notes) ? 'Provisioning database on cPanel.' : $database->notes,
        ])->save();

        $summary = [];

        $this->client->ping($server);
        $summary[] = 'Validated the cPanel API connection.';

        $this->client->createDatabase($server, $qualifiedDatabase);
        $summary[] = sprintf('Created database %s.', $qualifiedDatabase);

        $this->client->createDatabaseUser($server, $qualifiedUser, $password);
        $summary[] = sprintf('Created user %s.', $qualifiedUser);

        $this->client->setDatabasePrivileges($server, $qualifiedDatabase, $qualifiedUser, 'ALL PRIVILEGES');
        $summary[] = sprintf('Granted all privileges on %s to %s.', $qualifiedDatabase, $qualifiedUser);

        $database->forceFill([
            'status' => 'provisioned',
            'provisioned_at' => now(),
            'last_synced_at' => now(),
            'last_error' => null,
            'notes' => trim((string) ($database->notes ?: 'Provisioned and linked to cPanel.')),
        ])->save();

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    public function delete(Database $database): array
    {
        $server = $database->server;

        if (! $server) {
            throw new RuntimeException('The database does not have a server configured.');
        }

        if ($server->connection_type !== 'cpanel') {
            throw new RuntimeException('Database removal is only available for cPanel servers.');
        }

        if (! filled($server->effectiveCpanelApiToken())) {
            throw new RuntimeException('The cPanel API token is required before database removal can run.');
        }

        $baseName = $this->baseName($database);
        $baseUser = $this->baseUser($database, $baseName);
        $qualifiedDatabase = $this->qualify($server->effectiveCpanelUsername(), $baseName, 64);
        $qualifiedUser = $this->qualify($server->effectiveCpanelUsername(), $baseUser, 32);
        $summary = [];

        $this->client->ping($server);
        $summary[] = 'Validated the cPanel API connection.';

        try {
            $this->client->revokeDatabasePrivileges($server, $qualifiedDatabase, $qualifiedUser);
            $summary[] = sprintf('Revoked privileges for %s.', $qualifiedUser);
        } catch (Throwable) {
            // Keep going so removal can still try to clean up the user and database.
        }

        try {
            $this->client->deleteDatabaseUser($server, $qualifiedUser);
            $summary[] = sprintf('Deleted user %s.', $qualifiedUser);
        } catch (Throwable) {
            // Keep going so removal can still try to delete the database.
        }

        $this->client->deleteDatabase($server, $qualifiedDatabase);
        $summary[] = sprintf('Deleted database %s.', $qualifiedDatabase);

        $database->forceFill([
            'status' => 'deleted',
            'last_synced_at' => now(),
            'last_error' => null,
            'notes' => trim((string) ($database->notes ?: 'Removed from cPanel.')),
        ])->save();

        return $summary;
    }

    protected function baseName(Database $database): string
    {
        $name = trim((string) $database->name);

        if ($name === '') {
            $name = $database->site?->name ?: 'database';
        }

        return $this->sanitizeIdentifier($name, 'database', 32);
    }

    protected function baseUser(Database $database, string $fallback): string
    {
        $username = trim((string) $database->username);

        if ($username === '') {
            $username = $fallback;
        }

        return $this->sanitizeIdentifier($username, 'dbuser', 32);
    }

    protected function password(Database $database): string
    {
        $password = trim((string) $database->password);

        if ($password !== '') {
            return $password;
        }

        return Str::random(24);
    }

    protected function qualify(?string $prefix, string $value, int $maxLength): string
    {
        $prefix = $this->sanitizeIdentifier((string) $prefix, 'cpanel', 16);
        $value = $this->sanitizeIdentifier($value, 'database', $maxLength);

        if ($prefix === '') {
            return Str::limit($value, $maxLength, '');
        }

        return Str::limit(sprintf('%s_%s', $prefix, $value), $maxLength, '');
    }

    protected function sanitizeIdentifier(string $value, string $fallback, int $maxLength): string
    {
        $value = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->limit($maxLength, '')
            ->toString();

        return $value !== '' ? $value : Str::limit($fallback, $maxLength, '');
    }
}
