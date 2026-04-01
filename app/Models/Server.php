<?php

namespace App\Models;

use App\Services\AppSettings;
use Illuminate\Database\Eloquent\Builder;
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
        'team_id',
        'name',
        'ip_address',
        'provider_type',
        'provider_reference',
        'provider_region',
        'provider_metadata',
        'can_manage_domains',
        'can_manage_vhosts',
        'can_manage_dns',
        'can_manage_ssl',
        'vhost_config_path',
        'vhost_enabled_path',
        'vhost_reload_command',
        'connection_type',
        'ssh_credential_profile_id',
        'cpanel_credential_profile_id',
        'dns_credential_profile_id',
        'metrics',
        'status',
        'last_connected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ip_address' => 'string',
            'provider_type' => 'string',
            'provider_reference' => 'string',
            'provider_region' => 'string',
            'provider_metadata' => 'array',
            'can_manage_domains' => 'boolean',
            'can_manage_vhosts' => 'boolean',
            'can_manage_dns' => 'boolean',
            'can_manage_ssl' => 'boolean',
            'vhost_config_path' => 'string',
            'vhost_enabled_path' => 'string',
            'vhost_reload_command' => 'string',
            'connection_type' => 'string',
            'ssh_credential_profile_id' => 'integer',
            'cpanel_credential_profile_id' => 'integer',
            'dns_credential_profile_id' => 'integer',
            'metrics' => 'array',
            'last_connected_at' => 'datetime',
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

    public function sshCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'ssh_credential_profile_id');
    }

    public function cpanelCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'cpanel_credential_profile_id');
    }

    public function dnsCredentialProfile(): BelongsTo
    {
        return $this->belongsTo(CredentialProfile::class, 'dns_credential_profile_id');
    }

    public function effectiveCredentialProfile(string $type): ?CredentialProfile
    {
        return match ($type) {
            'ssh' => $this->sshCredentialProfile ?: app(AppSettings::class)->defaultSshCredentialProfile(),
            'cpanel' => $this->cpanelCredentialProfile ?: app(AppSettings::class)->defaultCpanelCredentialProfile(),
            'dns' => $this->dnsCredentialProfile ?: app(AppSettings::class)->defaultDnsCredentialProfile(),
            default => null,
        };
    }

    protected function credentialProfileSetting(string $type, array $keys, mixed $fallback = null): mixed
    {
        $profile = $this->effectiveCredentialProfile($type);

        if (! $profile) {
            return $fallback;
        }

        foreach ($keys as $key) {
            $value = data_get($profile->settings, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return $fallback;
    }

    public function effectiveSshUser(): ?string
    {
        return (string) $this->credentialProfileSetting('ssh', ['username', 'ssh_user'], 'root');
    }

    public function effectiveSshPort(): int
    {
        return (int) $this->credentialProfileSetting('ssh', ['port', 'ssh_port'], 22);
    }

    public function effectiveSshKey(): ?string
    {
        return $this->credentialProfileSetting('ssh', ['private_key', 'ssh_key'], null);
    }

    public function effectiveSudoPassword(): ?string
    {
        return $this->credentialProfileSetting('ssh', ['password', 'sudo_password', 'passphrase'], null);
    }

    public function getSshUserAttribute(): ?string
    {
        return $this->effectiveSshUser();
    }

    public function getSshPortAttribute(): int
    {
        return $this->effectiveSshPort();
    }

    public function getSshKeyAttribute(): ?string
    {
        return $this->effectiveSshKey();
    }

    public function getSudoPasswordAttribute(): ?string
    {
        return $this->effectiveSudoPassword();
    }

    public function getCpanelUsernameAttribute(): ?string
    {
        return $this->effectiveCpanelUsername();
    }

    public function getCpanelApiTokenAttribute(): ?string
    {
        return $this->effectiveCpanelApiToken();
    }

    public function getCpanelApiPortAttribute(): int
    {
        return $this->effectiveCpanelApiPort();
    }

    public function getDnsProviderAttribute(): string
    {
        return $this->effectiveDnsProvider();
    }

    public function getDnsZoneIdAttribute(): ?string
    {
        return $this->effectiveDnsZoneId();
    }

    public function getDnsApiTokenAttribute(): ?string
    {
        return $this->effectiveDnsApiToken();
    }

    public function getDnsProxyRecordsAttribute(): bool
    {
        return $this->effectiveDnsProxyRecords();
    }

    public function getUsernameAttribute(): ?string
    {
        return $this->ssh_user;
    }

    public function getPortAttribute(): int
    {
        return $this->ssh_port;
    }

    public function getHostAttribute(): ?string
    {
        return $this->ip_address;
    }

    public function effectiveCpanelUsername(): ?string
    {
        return $this->credentialProfileSetting('cpanel', ['username', 'cpanel_username'], null);
    }

    public function effectiveCpanelApiToken(): ?string
    {
        return $this->credentialProfileSetting('cpanel', ['api_token', 'cpanel_api_token'], null);
    }

    public function effectiveCpanelApiPort(): int
    {
        return (int) $this->credentialProfileSetting('cpanel', ['api_port', 'cpanel_api_port'], 2083);
    }

    public function effectiveDnsProvider(): string
    {
        return (string) $this->credentialProfileSetting('dns', ['provider', 'dns_provider'], 'manual');
    }

    public function effectiveDnsZoneId(): ?string
    {
        return $this->credentialProfileSetting('dns', ['zone_id', 'dns_zone_id'], null);
    }

    public function effectiveDnsApiToken(): ?string
    {
        return $this->credentialProfileSetting('dns', ['api_token', 'dns_api_token'], null);
    }

    public function effectiveDnsProxyRecords(): bool
    {
        $profileValue = $this->credentialProfileSetting('dns', ['proxy_records', 'dns_proxy_records'], null);

        return $profileValue !== null ? (bool) $profileValue : true;
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

    public function terminalSessions(): HasMany
    {
        return $this->hasMany(ServerTerminalSession::class)->latest('started_at');
    }

    public function activeTerminalSession(): HasMany
    {
        return $this->terminalSessions()->where('status', 'open');
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

    /**
     * @return array<string, string>
     */
    public static function dnsProviderOptions(): array
    {
        return [
            'manual' => 'Manual / Custom',
            'cloudflare' => 'Cloudflare',
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

    public function getDnsProviderLabelAttribute(): string
    {
        $provider = $this->effectiveDnsProvider();

        return static::dnsProviderOptions()[$provider] ?? ucwords(str_replace(['_', '-'], ' ', (string) $provider));
    }

    public function getDnsProviderBadgeAttribute(): string
    {
        return match ($this->effectiveDnsProvider()) {
            'cloudflare' => 'cloudflare',
            'manual' => 'manual',
            default => 'custom',
        };
    }

    public function getDnsProviderSummaryAttribute(): string
    {
        return match ($this->effectiveDnsProvider()) {
            'cloudflare' => 'Cloudflare can manage the DNS zone and record updates for this server.',
            'manual' => 'DNS is managed manually outside of the app.',
            default => 'A custom DNS provider can be wired in later.',
        };
    }

    public function getCapabilitySummaryAttribute(): string
    {
        $items = [];

        if ($this->can_manage_domains) {
            $items[] = 'domains';
        }

        if ($this->can_manage_vhosts) {
            $items[] = 'vhosts';
        }

        if ($this->can_manage_dns) {
            $items[] = 'dns';
        }

        if ($this->can_manage_ssl) {
            $items[] = 'ssl';
        }

        if (empty($items)) {
            return 'No provider capabilities have been enabled yet.';
        }

        return sprintf('This server can manage %s.', implode(', ', $items));
    }

    public function getSshCredentialProfileLabelAttribute(): string
    {
        return $this->sshCredentialProfile?->name
            ?? app(AppSettings::class)->defaultSshCredentialProfile()?->name
            ?? 'No SSH profile';
    }

    public function getCpanelCredentialProfileLabelAttribute(): string
    {
        return $this->cpanelCredentialProfile?->name
            ?? app(AppSettings::class)->defaultCpanelCredentialProfile()?->name
            ?? 'No cPanel profile';
    }

    public function getDnsCredentialProfileLabelAttribute(): string
    {
        return $this->dnsCredentialProfile?->name
            ?? app(AppSettings::class)->defaultDnsCredentialProfile()?->name
            ?? 'No DNS profile';
    }

    public function getVhostConfigPathAttribute(): string
    {
        if (filled($this->attributes['vhost_config_path'] ?? null)) {
            return (string) $this->attributes['vhost_config_path'];
        }

        return match ($this->vhost_engine) {
            'apache' => sprintf('/etc/apache2/sites-available/%s.conf', $this->vhostSlug()),
            default => sprintf('/etc/nginx/sites-available/%s.conf', $this->vhostSlug()),
        };
    }

    public function getVhostEnabledPathAttribute(): string
    {
        if (filled($this->attributes['vhost_enabled_path'] ?? null)) {
            return (string) $this->attributes['vhost_enabled_path'];
        }

        return match ($this->vhost_engine) {
            'apache' => sprintf('/etc/apache2/sites-enabled/%s.conf', $this->vhostSlug()),
            default => sprintf('/etc/nginx/sites-enabled/%s.conf', $this->vhostSlug()),
        };
    }

    public function getVhostReloadCommandAttribute(): string
    {
        if (filled($this->attributes['vhost_reload_command'] ?? null)) {
            return (string) $this->attributes['vhost_reload_command'];
        }

        return match ($this->vhost_engine) {
            'apache' => 'systemctl reload apache2 || systemctl reload httpd',
            default => 'systemctl reload nginx',
        };
    }

    public function getVhostEngineAttribute(): string
    {
        return in_array($this->provider_type, ['aws', 'digitalocean', 'hetzner', 'vultr', 'linode', 'local', 'manual'], true)
            ? 'nginx'
            : 'apache';
    }

    protected function vhostSlug(): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($this->provider_reference ?: $this->name ?: 'site'))) ?: 'site';
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
            'cpanel' => sprintf('cpanel@%s:%s$ ', $this->name, $this->effectiveCpanelApiPort() ?: 2083),
            'local' => sprintf('local@%s:%s$ ', gethostname() ?: 'localhost', base_path()),
            default => sprintf('%s@%s:%s$ ', $this->effectiveSshUser() ?: 'root', $this->name, $this->effectiveSshPort() ?: 22),
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
    }

    public function setProviderTypeAttribute(mixed $value): void
    {
        $this->attributes['provider_type'] = $value ?: 'manual';
    }
}
