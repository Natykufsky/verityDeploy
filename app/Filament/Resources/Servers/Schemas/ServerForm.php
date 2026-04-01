<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Team;
use App\Services\AppSettings;
use App\Services\Cpanel\CpanelApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Throwable;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn (): ?int => auth()->id()),
                Tabs::make('Server Settings')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge('Base')
                            ->badgeColor('primary')
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('ip_address')
                                    ->label('IP address')
                                    ->required(),
                                TextInput::make('ssh_port')
                                    ->label('SSH port')
                                    ->required()
                                    ->numeric()
                                    ->default(fn (): int => app(AppSettings::class)->defaultSshPort())
                                    ->suffixAction(
                                        Action::make('discoverCpanelSshPort')
                                            ->label('Discover')
                                            ->icon('heroicon-o-magnifying-glass')
                                            ->visible(fn (?Server $record, Get $get): bool => $get('connection_type') === 'cpanel' && filled($record))
                                            ->action(function (?Server $record, Set $set): void {
                                                if (! $record) {
                                                    Notification::make()
                                                        ->title('Save the server first')
                                                        ->body('Create or update the cPanel server before discovering its SSH port.')
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                try {
                                                    $port = app(CpanelApiClient::class)->discoverSshPort($record->fresh());

                                                    $set('ssh_port', $port);
                                                    $record->update([
                                                        'ssh_port' => $port,
                                                    ]);

                                                    Notification::make()
                                                        ->title('SSH port discovered')
                                                        ->body("The cPanel account SSH port was set to {$port}.")
                                                        ->success()
                                                        ->send();
                                                } catch (Throwable $throwable) {
                                                    Notification::make()
                                                        ->title('Unable to discover SSH port')
                                                        ->body($throwable->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    )
                                    ->helperText('Use Discover after saving a cPanel server to fetch the account SSH port from the API.'),
                                TextInput::make('ssh_user')
                                    ->label('SSH user')
                                    ->helperText('Leave blank to inherit the SSH user from your selected Credential Profile.'),
                                Select::make('ssh_credential_profile_id')
                                    ->label('SSH credential profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('ssh')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultSshCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('Use default or enter manually')
                                    ->helperText('Select a shared SSH profile to avoid repeating host, key, and password details.')
                                    ->columnSpanFull(),
                                TextInput::make('cpanel_username')
                                    ->label('cPanel username')
                                    ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel')
                                    ->helperText('Leave blank to inherit the cPanel username from your selected Credential Profile.')
                                    ->columnSpanFull(),
                                Select::make('team_id')
                                    ->label('Team')
                                    ->options(fn (): array => Team::query()
                                        ->accessibleTo()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->placeholder('Personal workspace')
                                    ->helperText('Assign this server to a team so other members can share access and deploys.'),
                                Select::make('connection_type')
                                    ->options([
                                        'ssh_key' => 'SSH key',
                                        'password' => 'Password',
                                        'local' => 'Local',
                                        'cpanel' => 'cPanel',
                                    ])
                                    ->default('ssh_key')
                                    ->required(),
                                TextInput::make('status')
                                    ->required()
                                    ->default('offline'),
                                DateTimePicker::make('last_connected_at'),
                                View::make('filament.servers.connection-mode-help')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Security')
                            ->badge('Access')
                            ->badgeColor('warning')
                            ->schema([
                                Textarea::make('ssh_key')
                                    ->label('SSH private key')
                                    ->rows(12)
                                    ->columnSpanFull()
                                    ->helperText('Stored encrypted. Leave blank to inherit the SSH key from your selected Credential Profile. Or use the "generate key" action to create one.'),
                                TextInput::make('sudo_password')
                                    ->label('Sudo / SSH password')
                                    ->password()
                                    ->revealable()
                                    ->columnSpanFull()
                                    ->helperText('Leave blank to inherit the password from your selected Credential Profile.'),
                                Textarea::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                        Tab::make('cPanel')
                            ->badge('API')
                            ->badgeColor('info')
                            ->schema([
                                TextInput::make('cpanel_api_token')
                                    ->label('cPanel API token')
                                    ->password()
                                    ->revealable()
                                    ->columnSpanFull()
                                    ->suffixAction(
                                        Action::make('testCpanelApi')
                                            ->label('Test API')
                                            ->icon('heroicon-o-bolt')
                                            ->visible(fn (?Server $record, Get $get): bool => $get('connection_type') === 'cpanel' && filled($record))
                                            ->action(function (?Server $record, Get $get): void {
                                                if (! $record) {
                                                    Notification::make()
                                                        ->title('Save the server first')
                                                        ->body('Create or update the cPanel server before testing the API token.')
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                if ((int) ($get('cpanel_api_port') ?: 2083) === (int) ($get('ssh_port') ?: 22)) {
                                                    Notification::make()
                                                        ->title('Wrong cPanel API port')
                                                        ->body('The cPanel API port matches the SSH port. Change it to the cPanel HTTPS/API port, usually 2083, then try again.')
                                                        ->danger()
                                                        ->send();

                                                    return;
                                                }

                                                try {
                                                    app(CpanelApiClient::class)->ping($record->fresh());

                                                    $record->update([
                                                        'status' => 'online',
                                                        'last_connected_at' => now(),
                                                    ]);

                                                    Notification::make()
                                                        ->title('cPanel API responded')
                                                        ->body('The cPanel token and API port are reachable.')
                                                        ->success()
                                                        ->send();
                                                } catch (Throwable $throwable) {
                                                    Notification::make()
                                                        ->title('Unable to reach the cPanel API')
                                                        ->body($throwable->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    )
                                    ->helperText('Stored encrypted. Leave blank to inherit the cPanel API token from your selected Credential Profile.'),
                                TextInput::make('cpanel_api_port')
                                    ->label('cPanel API port')
                                    ->numeric()
                                    ->default(2083)
                                    ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel')
                                    ->helperText(fn (Get $get): string => ((int) ($get('cpanel_api_port') ?: 2083) === (int) ($get('ssh_port') ?: 22))
                                        ? 'This must be the cPanel HTTPS/API port, not the SSH port. If it matches SSH, change it to the actual cPanel API port (usually 2083).'
                                        : 'This should be the cPanel HTTPS/API port. It is usually 2083 and should not match the SSH port.'),
                                Select::make('cpanel_credential_profile_id')
                                    ->label('cPanel credential profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('cpanel')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultCpanelCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('Use default or enter manually')
                                    ->helperText('Select a shared cPanel profile to avoid repeating the account token and login details.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Provider')
                            ->badge('Infra')
                            ->badgeColor('success')
                            ->schema([
                                Select::make('provider_type')
                                    ->label('Provider type')
                                    ->options(Server::providerOptions())
                                    ->default('manual')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state === 'cpanel') {
                                            $set('can_manage_domains', true);
                                            $set('can_manage_vhosts', false);
                                            $set('can_manage_dns', true);
                                            $set('can_manage_ssl', true);

                                            return;
                                        }

                                        $set('can_manage_domains', true);
                                        $set('can_manage_vhosts', true);
                                        $set('can_manage_dns', false);
                                        $set('can_manage_ssl', true);
                                    })
                                    ->helperText('This identifies which cloud or hosting provider owns the machine. It helps with inventory, reporting, and future provider-specific automation.'),
                                TextInput::make('provider_reference')
                                    ->label('Provider reference')
                                    ->placeholder('droplet-12345 / i-0abc123 / server-789')
                                    ->helperText('Store the vendor-specific identifier here so you can match this server to the infrastructure console.'),
                                TextInput::make('provider_region')
                                    ->label('Provider region')
                                    ->placeholder('fra1 / us-east-1 / ewr1')
                                    ->helperText('Use the vendor region or datacenter name so the server is easy to locate later.'),
                                Select::make('dns_credential_profile_id')
                                    ->label('DNS credential profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('dns')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultDnsCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('Use default or enter manually')
                                    ->helperText('Select a shared DNS profile to avoid repeating API tokens and zone details.')
                                    ->columnSpanFull(),
                                KeyValue::make('provider_metadata')
                                    ->label('Provider metadata')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->columnSpanFull()
                                    ->helperText('Add any extra provider details such as plan, tags, cluster, or notes.'),
                                Section::make('Capabilities')
                                    ->schema([
                                        Toggle::make('can_manage_domains')
                                            ->label('Can manage domains')
                                            ->helperText('Enable this when the server can create addon domains or subdomains.')
                                            ->default(false),
                                        Toggle::make('can_manage_vhosts')
                                            ->label('Can manage vhosts')
                                            ->helperText('Enable this when the server can generate or apply nginx/apache virtual hosts.')
                                            ->default(false),
                                        Toggle::make('can_manage_dns')
                                            ->label('Can manage DNS')
                                            ->helperText('Enable this when the server or provider can manage DNS records.')
                                            ->default(false),
                                        Toggle::make('can_manage_ssl')
                                            ->label('Can manage SSL')
                                            ->helperText('Enable this when the server or provider can issue or renew certificates.')
                                            ->default(false),
                                    ])
                                    ->columns(2),
                                Section::make('VPS vhost paths')
                                    ->schema([
                                        TextInput::make('vhost_config_path')
                                            ->label('Vhost config path')
                                            ->placeholder('/etc/nginx/sites-available/site.conf')
                                            ->helperText('Optional override for the file path shown in the VPS repair plan.'),
                                        TextInput::make('vhost_enabled_path')
                                            ->label('Vhost enabled path')
                                            ->placeholder('/etc/nginx/sites-enabled/site.conf')
                                            ->helperText('Optional override for the active or enabled path used by the web server.'),
                                        TextInput::make('vhost_reload_command')
                                            ->label('Reload command')
                                            ->placeholder('systemctl reload nginx')
                                            ->helperText('Optional override for the command used to reload the web server after config changes.'),
                                    ])
                                    ->columns(1)
                                    ->visible(fn (?Server $record, Get $get): bool => (($get('connection_type') ?? $record?->connection_type) !== 'cpanel') && (bool) ($get('can_manage_vhosts') ?? $record?->can_manage_vhosts)),
                                Section::make('DNS provider')
                                    ->schema([
                                        Select::make('dns_provider')
                                            ->label('DNS provider')
                                            ->options(Server::dnsProviderOptions())
                                            ->default('manual')
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if ($state === 'cloudflare') {
                                                    $set('can_manage_dns', true);
                                                }
                                            })
                                            ->helperText('Choose the DNS provider that can manage zones and records for this server.'),
                                        TextInput::make('dns_zone_id')
                                            ->label('Cloudflare zone ID')
                                            ->visible(fn (Get $get): bool => $get('dns_provider') === 'cloudflare')
                                            ->helperText('Leave blank to inherit the zone ID from your selected DNS Credential Profile.'),
                                        TextInput::make('dns_api_token')
                                            ->label('Cloudflare API token')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn (Get $get): bool => $get('dns_provider') === 'cloudflare')
                                            ->helperText('Leave blank to inherit the API token from your selected Credential Profile.'),
                                        Toggle::make('dns_proxy_records')
                                            ->label('Proxy DNS records')
                                            ->default(true)
                                            ->visible(fn (Get $get): bool => $get('dns_provider') === 'cloudflare')
                                            ->helperText('Turn this off if you want DNS records to resolve directly to the origin server.'),
                                    ])
                                    ->columns(2),
                                View::make('filament.servers.provider-mode-help')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Audit')
                            ->badge('Log')
                            ->badgeColor('gray')
                            ->schema([
                                DateTimePicker::make('last_connected_at'),
                                TextInput::make('status')
                                    ->required()
                                    ->default('offline'),
                                Textarea::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
