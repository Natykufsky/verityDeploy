<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'ip_address',
        'ssh_port',
        'ssh_user',
        'connection_type',
        'ssh_key',
        'sudo_password',
        'cpanel_api_token',
        'cpanel_api_port',
        'metrics',
        'host',
        'port',
        'username',
        'private_key',
        'passphrase',
        'status',
        'last_connected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ip_address' => 'string',
            'ssh_port' => 'integer',
            'ssh_user' => 'string',
            'connection_type' => 'string',
            'ssh_key' => 'encrypted',
            'sudo_password' => 'encrypted',
            'cpanel_api_token' => 'encrypted',
            'metrics' => 'array',
            'private_key' => 'encrypted',
            'passphrase' => 'encrypted',
            'last_connected_at' => 'datetime',
            'port' => 'integer',
            'cpanel_api_port' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function deployments(): HasManyThrough
    {
        return $this->hasManyThrough(Deployment::class, Site::class);
    }

    public function connectionTests(): HasMany
    {
        return $this->hasMany(ServerConnectionTest::class)->latest('tested_at');
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ServerHealthCheck::class)->latest('tested_at');
    }

    public function cpanelWizardRuns(): HasMany
    {
        return $this->hasMany(CpanelWizardRun::class)->latest('started_at');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOperationalTimelineAttribute(): array
    {
        $connectionTests = $this->connectionTests()
            ->latest('tested_at')
            ->limit(10)
            ->get()
            ->map(fn (ServerConnectionTest $test): array => [
                'type' => 'connection',
                'title' => 'Connection test',
                'status' => $test->status,
                'tested_at' => $test->tested_at,
                'command' => $test->command,
                'output' => $test->output,
                'error_message' => $test->error_message,
                'metrics' => null,
            ]);

        $healthChecks = $this->healthChecks()
            ->latest('tested_at')
            ->limit(10)
            ->get()
            ->map(fn (ServerHealthCheck $check): array => [
                'type' => 'health',
                'title' => 'Health check',
                'status' => $check->status,
                'tested_at' => $check->tested_at,
                'command' => 'uptime && free -m && df -h /',
                'output' => $check->output,
                'error_message' => $check->error_message,
                'metrics' => $check->metrics,
            ]);

        return $connectionTests
            ->concat($healthChecks)
            ->filter(fn (array $item): bool => filled($item['tested_at'] ?? null))
            ->sortByDesc(fn (array $item): mixed => $item['tested_at'] instanceof \DateTimeInterface
                ? $item['tested_at']->getTimestamp()
                : strtotime((string) $item['tested_at']))
            ->values()
            ->all();
    }

    public function setIpAddressAttribute(mixed $value): void
    {
        $this->attributes['ip_address'] = $value;
        $this->attributes['host'] = $value;
    }

    public function setSshPortAttribute(mixed $value): void
    {
        $this->attributes['ssh_port'] = $value;
        $this->attributes['port'] = $value;
    }

    public function setSshUserAttribute(mixed $value): void
    {
        $this->attributes['ssh_user'] = $value;
        $this->attributes['username'] = $value;
    }

    public function setSshKeyAttribute(mixed $value): void
    {
        $this->attributes['ssh_key'] = $value;
        $this->attributes['private_key'] = $value;
    }

    public function setSudoPasswordAttribute(mixed $value): void
    {
        $this->attributes['sudo_password'] = $value;
        $this->attributes['passphrase'] = $value;
    }

    public function setHostAttribute(mixed $value): void
    {
        $this->setIpAddressAttribute($value);
    }

    public function setPortAttribute(mixed $value): void
    {
        $this->setSshPortAttribute($value);
    }

    public function setUsernameAttribute(mixed $value): void
    {
        $this->setSshUserAttribute($value);
    }

    public function setPrivateKeyAttribute(mixed $value): void
    {
        $this->setSshKeyAttribute($value);
    }

    public function setPassphraseAttribute(mixed $value): void
    {
        $this->setSudoPasswordAttribute($value);
    }
}
