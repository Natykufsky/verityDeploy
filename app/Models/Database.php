<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Database extends Model
{
    protected $fillable = [
        'site_id',
        'server_id',
        'name',
        'username',
        'password',
        'status',
        'provisioned_at',
        'last_synced_at',
        'last_error',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'password' => EncryptedTextOrPlain::class,
            'provisioned_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error' => 'string',
            'notes' => 'string',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function cpanelDatabaseName(): ?string
    {
        $server = $this->server;

        if (! $server) {
            return null;
        }

        return $this->qualifyCpanelName($this->baseName(), (string) $server->effectiveCpanelUsername(), 64);
    }

    public function cpanelUsername(): ?string
    {
        $server = $this->server;

        if (! $server) {
            return null;
        }

        return $this->qualifyCpanelName($this->baseUsername(), (string) $server->effectiveCpanelUsername(), 32);
    }

    public function baseName(): string
    {
        $name = trim((string) ($this->attributes['name'] ?? ''));

        return $this->normalizeIdentifier($name, 'database');
    }

    public function baseUsername(): string
    {
        $username = trim((string) ($this->attributes['username'] ?? ''));

        if ($username === '') {
            $username = $this->baseName();
        }

        return $this->normalizeIdentifier($username, 'dbuser');
    }

    protected function qualifyCpanelName(string $name, string $prefix, int $maxLength): string
    {
        $name = $this->normalizeIdentifier($name, 'database');
        $prefix = $this->normalizeIdentifier($prefix, 'cpanel');

        if ($name === '') {
            $name = 'database';
        }

        if ($prefix === '') {
            return $name;
        }

        $qualified = sprintf('%s_%s', $prefix, $name);

        return Str::limit($qualified, $maxLength, '');
    }

    protected function normalizeIdentifier(string $value, string $fallback): string
    {
        $value = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();

        return $value !== '' ? $value : $fallback;
    }
}
