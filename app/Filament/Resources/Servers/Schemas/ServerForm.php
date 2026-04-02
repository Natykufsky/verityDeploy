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
                                Select::make('connection_type')
                                    ->label('Connection type')
                                    ->options([
                                        'ssh_key' => 'SSH key',
                                        'password' => 'Password',
                                        'local' => 'Local',
                                        'cpanel' => 'cPanel',
                                    ])
                                    ->default('ssh_key')
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('ip_address')
                                    ->label('IP address')
                                    ->required()
                                    ->visible(fn (Get $get): bool => $get('connection_type') !== 'local'),
                                Select::make('cpanel_credential_profile_id')
                                    ->label('cPanel credential profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('cpanel')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultCpanelCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('Select cPanel API token')
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel'),
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
                                                        ->title('Save record first')
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
                                    ->visible(fn (Get $get): bool => in_array($get('connection_type'), ['ssh_key', 'password', 'cpanel'])),
                                Select::make('ssh_credential_profile_id')
                                    ->label('SSH credential profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('ssh')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultSshCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('Select SSH key or password')
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => in_array($get('connection_type'), ['ssh_key', 'password', 'cpanel'])),
                                Select::make('team_id')
                                    ->label('Owner team')
                                    ->options(fn (): array => Team::query()
                                        ->accessibleTo()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->placeholder('Personal workspace')
                                    ->columnSpanFull(),
                                TextInput::make('status')
                                    ->required()
                                    ->default('offline')
                                    ->disabled(),
                                DateTimePicker::make('last_connected_at')
                                    ->disabled(),
                            ])
                            ->columns(2),

                        Tab::make('Infrastructure')
                            ->badge('Cloud')
                            ->badgeColor('success')
                            ->schema([
                                Select::make('provider_type')
                                    ->label('Cloud provider')
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
                                    }),
                                TextInput::make('provider_reference')
                                    ->label('Node identifier')
                                    ->placeholder('e.g. i-0abc123'),
                                TextInput::make('provider_region')
                                    ->label('Region')
                                    ->placeholder('e.g. fra1'),
                                Select::make('dns_credential_profile_id')
                                    ->label('DNS API profile')
                                    ->options(fn (): array => CredentialProfile::query()->ofType('dns')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => app(AppSettings::class)->defaultDnsCredentialProfileId())
                                    ->searchable()
                                    ->placeholder('None')
                                    ->columnSpanFull(),
                                KeyValue::make('provider_metadata')
                                    ->label('Custom metadata')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->columnSpanFull(),
                                Section::make('Service Capabilities')
                                    ->schema([
                                        Toggle::make('can_manage_domains')
                                            ->label('Domain management')
                                            ->default(false),
                                        Toggle::make('can_manage_vhosts')
                                            ->label('Vhost management')
                                            ->default(false),
                                        Toggle::make('can_manage_dns')
                                            ->label('DNS management')
                                            ->default(false),
                                        Toggle::make('can_manage_ssl')
                                            ->label('SSL management')
                                            ->default(false),
                                    ])
                                    ->columns(2),
                                Section::make('Advanced Vhost Paths')
                                    ->schema([
                                        TextInput::make('vhost_config_path')
                                            ->label('Config path')
                                            ->placeholder('/etc/nginx/sites-available/site.conf'),
                                        TextInput::make('vhost_enabled_path')
                                            ->label('Enabled path')
                                            ->placeholder('/etc/nginx/sites-enabled/site.conf'),
                                        TextInput::make('vhost_reload_command')
                                            ->label('Reload command')
                                            ->placeholder('systemctl reload nginx'),
                                    ])
                                    ->columns(1)
                                    ->visible(fn (?Server $record, Get $get): bool => (($get('connection_type') ?? $record?->connection_type) !== 'cpanel') && (bool) ($get('can_manage_vhosts') ?? $record?->can_manage_vhosts)),
                                Section::make('DNS Authority')
                                    ->schema([
                                        Select::make('dns_provider')
                                            ->label('DNS Authority')
                                            ->options(Server::dnsProviderOptions())
                                            ->default('manual')
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if ($state === 'cloudflare') {
                                                    $set('can_manage_dns', true);
                                                }
                                            }),
                                    ])
                                    ->columns(2),
                            ])
                            ->columns(2),
                        Tab::make('Audit')
                            ->badge('History')
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
