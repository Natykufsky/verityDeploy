<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Crypt;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'ip_address',
        'ssh_port',
        'ssh_user',
        'cpanel_username',
        'provider_type',
        'provider_reference',
        'provider_region',
        'provider_metadata',
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
            'cpanel_username' => 'string',
            'provider_type' => 'string',
            'provider_reference' => 'string',
            'provider_region' => 'string',
            'provider_metadata' => 'array',
            'connection_type' => 'string',
            'ssh_key' => EncryptedTextOrPlain::class,
            'sudo_password' => EncryptedTextOrPlain::class,
            'cpanel_api_token' => EncryptedTextOrPlain::class,
            'metrics' => 'array',
            'private_key' => EncryptedTextOrPlain::class,
            'passphrase' => EncryptedTextOrPlain::class,
            'last_connected_at' => 'datetime',
            'port' => 'integer',
            'cpanel_api_port' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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

    public function terminalRuns(): HasMany
    {
        return $this->hasMany(ServerTerminalRun::class)->latest('started_at');
    }

    public function terminalPresets(): HasMany
    {
        return $this->hasMany(ServerTerminalPreset::class)->latest('updated_at');
    }

    /**
     * @return array<string, string>
     */
    public static function providerOptions(): array
    {
        return [
            'manual' => 'Manual / Custom',
            'digitalocean' => 'DigitalOcean',
            'aws' => 'Amazon Web Services',
            'hetzner' => 'Hetzner',
            'vultr' => 'Vultr',
            'linode' => 'Linode',
            'cpanel' => 'cPanel',
            'local' => 'Local machine',
        ];
    }

    public function getProviderLabelAttribute(): string
    {
        return static::providerOptions()[$this->provider_type] ?? ucwords(str_replace(['_', '-'], ' ', (string) $this->provider_type));
    }

    public function getProviderBadgeColorAttribute(): string
    {
        return match ($this->provider_type) {
            'digitalocean' => 'info',
            'aws' => 'warning',
            'hetzner' => 'danger',
            'vultr', 'linode' => 'primary',
            'cpanel' => 'success',
            'local' => 'gray',
            default => 'slate',
        };
    }

    public function getProviderSummaryAttribute(): string
    {
        $reference = filled($this->provider_reference) ? $this->provider_reference : 'no provider reference set';
        $region = filled($this->provider_region) ? $this->provider_region : 'no region set';

        return match ($this->provider_type) {
            'digitalocean' => sprintf('DigitalOcean droplet %s in %s.', $reference, $region),
            'aws' => sprintf('AWS instance %s in %s.', $reference, $region),
            'hetzner' => sprintf('Hetzner server %s in %s.', $reference, $region),
            'vultr' => sprintf('Vultr instance %s in %s.', $reference, $region),
            'linode' => sprintf('Linode instance %s in %s.', $reference, $region),
            'cpanel' => sprintf('cPanel account %s in %s.', $reference, $region),
            'local' => 'Local dashboard-hosted server used for packaging and self-hosted deploys.',
            default => sprintf('Custom or manually managed provider with reference %s and region %s.', $reference, $region),
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
            $query->where('user_id', $user->id)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->whereNotNull('team_id')
                        ->whereIn('team_id', $user->teams()->select('teams.id'));
                });
        });
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

    /**
     * @return array<string, mixed>
     */
    public function getProviderStateAttribute(): array
    {
        return [
            'type' => $this->provider_type,
            'label' => $this->provider_label,
            'summary' => $this->provider_summary,
            'reference' => $this->provider_reference,
            'region' => $this->provider_region,
            'metadata' => $this->provider_metadata ?? [],
        ];
    }

    public function getTerminalPromptAttribute(): string
    {
        return match ($this->connection_type) {
            'cpanel' => sprintf('cpanel@%s:%s$ ', $this->name, $this->ssh_port ?: $this->cpanel_api_port ?: 22),
            'local' => sprintf('local@%s:%s$ ', gethostname() ?: 'localhost', base_path()),
            default => sprintf('%s@%s:%s$ ', $this->ssh_user ?: 'root', $this->name, $this->ssh_port ?: 22),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function terminalPresetGroups(int $limit = 12, ?string $search = null, ?string $groupFilter = null, ?string $tagFilter = null): array
    {
        $search = filled($search) ? strtolower(trim((string) $search)) : null;
        $groupFilter = filled($groupFilter) ? strtolower(trim((string) $groupFilter)) : null;
        $tagFilter = filled($tagFilter) ? strtolower(trim((string) $tagFilter)) : null;

        return $this->terminalPresets()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->filter(function (ServerTerminalPreset $preset) use ($search, $groupFilter, $tagFilter): bool {
                $group = filled($preset->group_name) ? strtolower((string) $preset->group_name) : 'ungrouped';
                $tags = collect($preset->tags ?? [])->map(fn (string $tag): string => strtolower(trim($tag)));
                $haystack = strtolower(trim(implode(' ', array_filter([
                    (string) $preset->name,
                    (string) $preset->command,
                    (string) $preset->description,
                    (string) $preset->group_name,
                    implode(' ', $tags->all()),
                ]))));

                if ($search && ! str_contains($haystack, $search)) {
                    return false;
                }

                if ($groupFilter && $group !== $groupFilter) {
                    return false;
                }

                if ($tagFilter && ! $tags->contains($tagFilter)) {
                    return false;
                }

                return true;
            })
            ->groupBy(fn (ServerTerminalPreset $preset): string => filled($preset->group_name) ? (string) $preset->group_name : 'Ungrouped')
            ->map(function ($presets, string $group): array {
                return [
                    'group' => $group,
                    'presets' => collect($presets)->map(fn (ServerTerminalPreset $preset): array => [
                        'id' => $preset->id,
                        'name' => $preset->name,
                        'command' => $preset->command,
                        'description' => $preset->description,
                        'tags' => $preset->tags ?? [],
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function terminalPresetGroupOptions(int $limit = 24): array
    {
        return $this->terminalPresets()
            ->latest('updated_at')
            ->limit($limit)
            ->pluck('group_name')
            ->filter()
            ->map(fn (string $group): string => trim($group))
            ->unique(fn (string $group): string => strtolower($group))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function terminalPresetTagOptions(int $limit = 24): array
    {
        return $this->terminalPresets()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->flatMap(fn (ServerTerminalPreset $preset): array => $preset->tags ?? [])
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, command: string, description: string, source: string, tags: array<int, string>, group: string}>
     */
    public function terminalAutocompleteSuggestions(): array
    {
        $quickCommands = match ($this->connection_type) {
            'cpanel' => [
                ['label' => 'API ping', 'command' => 'ping', 'description' => 'Check the cPanel API path and token.', 'source' => 'Quick command', 'tags' => ['cpanel'], 'group' => 'Connection'],
                ['label' => 'Who am I', 'command' => 'whoami', 'description' => 'Confirm the account username returned by cPanel.', 'source' => 'Quick command', 'tags' => ['identity'], 'group' => 'Connection'],
            ],
            'local' => [
                ['label' => 'pwd', 'command' => 'pwd', 'description' => 'Show the dashboard server working directory.', 'source' => 'Quick command', 'tags' => ['files'], 'group' => 'Local'],
                ['label' => 'php -v', 'command' => 'php -v', 'description' => 'Check the local PHP version.', 'source' => 'Quick command', 'tags' => ['runtime'], 'group' => 'Local'],
                ['label' => 'composer -V', 'command' => 'composer -V', 'description' => 'Confirm Composer is available locally.', 'source' => 'Quick command', 'tags' => ['runtime'], 'group' => 'Local'],
            ],
            default => [
                ['label' => 'whoami', 'command' => 'whoami', 'description' => 'Confirm the SSH user on the remote server.', 'source' => 'Quick command', 'tags' => ['identity'], 'group' => 'SSH'],
                ['label' => 'uptime', 'command' => 'uptime', 'description' => 'Check server load and uptime.', 'source' => 'Quick command', 'tags' => ['system'], 'group' => 'SSH'],
                ['label' => 'df -h', 'command' => 'df -h', 'description' => 'Inspect disk usage.', 'source' => 'Quick command', 'tags' => ['files'], 'group' => 'SSH'],
            ],
        };

        $presets = $this->terminalPresets()
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(fn (ServerTerminalPreset $preset): array => [
                'label' => $preset->name,
                'command' => $preset->command,
                'description' => $preset->description ?: 'Saved preset',
                'source' => 'Preset',
                'tags' => $preset->tags ?? [],
                'group' => filled($preset->group_name) ? (string) $preset->group_name : 'Ungrouped',
            ])
            ->all();

        $history = $this->terminalRuns()
            ->where('status', 'successful')
            ->whereNotNull('command')
            ->latest('started_at')
            ->limit(10)
            ->pluck('command')
            ->map(fn (string $command): array => [
                'label' => $command,
                'command' => $command,
                'description' => 'From previous command history',
                'source' => 'History',
                'tags' => ['history'],
                'group' => 'History',
            ])
            ->all();

        return collect($quickCommands)
            ->merge($presets)
            ->merge($history)
            ->unique(fn (array $item): string => $item['command'])
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

    public function setCpanelUsernameAttribute(mixed $value): void
    {
        $this->attributes['cpanel_username'] = $value;
    }

    public function setSshKeyAttribute(mixed $value): void
    {
        $encrypted = filled($value) ? Crypt::encryptString((string) $value) : null;

        $this->attributes['ssh_key'] = $encrypted;
        $this->attributes['private_key'] = $encrypted;
    }

    public function setSudoPasswordAttribute(mixed $value): void
    {
        $encrypted = filled($value) ? Crypt::encryptString((string) $value) : null;

        $this->attributes['sudo_password'] = $encrypted;
        $this->attributes['passphrase'] = $encrypted;
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

    public function setProviderTypeAttribute(mixed $value): void
    {
        $this->attributes['provider_type'] = $value ?: 'manual';
    }
}
