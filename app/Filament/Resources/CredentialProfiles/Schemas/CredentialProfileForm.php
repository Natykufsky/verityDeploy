<?php

namespace App\Filament\Resources\CredentialProfiles\Schemas;

use App\Models\CredentialProfile;
use App\Services\Security\SshKeyService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class CredentialProfileForm
{
    /*
     * Form configuration.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile definition')
                    ->description('Assign a type and name to this shared credential set.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Profile name')
                            ->placeholder('e.g. Identity Key')
                            ->required()
                            ->maxLength(120),
                        Select::make('type')
                            ->label('Credential type')
                            ->required()
                            ->options(CredentialProfile::typeOptions())
                            ->live(),
                        TextInput::make('description')
                            ->label('Context / Notes')
                            ->placeholder('Where and why is this used?')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_default')
                            ->label('Mark as system default')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Enable this profile')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('SSH configuration')
                    ->description('Configure your SSH identity, including key management and terminal defaults.')
                    ->visible(fn (Get $get): bool => $get('type') === 'ssh')
                    ->schema([
                        TextInput::make('settings.username')
                            ->label('SSH User')
                            ->placeholder('root')
                            ->default('root')
                            ->dehydrated(true)
                            ->required(),
                        TextInput::make('settings.port')
                            ->label('SSH Port')
                            ->placeholder('22')
                            ->default('22')
                            ->numeric()
                            ->dehydrated(true)
                            ->required(),
                        TextInput::make('settings.passphrase')
                            ->label('Key passphrase')
                            ->placeholder('Leave empty if none')
                            ->password()
                            ->dehydrated(true)
                            ->revealable(),
                        TextInput::make('settings.sudo_password')
                            ->label('Sudo password')
                            ->placeholder('Optional')
                            ->password()
                            ->dehydrated(true)
                            ->revealable()
                            ->helperText('Used for commands requiring root privileges if the user is not root.'),

                        Textarea::make('settings.private_key')
                            ->label('Private key')
                            ->placeholder('-----BEGIN OPENSSH PRIVATE KEY-----')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->dehydrated(true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (filled($state)) {
                                    $publicKey = app(SshKeyService::class)->derivePublicKey($state, (string) $get('settings.passphrase'));
                                    if ($publicKey) {
                                        $set('settings.public_key', $publicKey);
                                    }
                                }
                            })
                            ->extraAttributes(['class' => 'font-mono text-sm']),

                        Actions::make([
                            Action::make('uploadKeyFile')
                                ->label('Upload key file')
                                ->icon('heroicon-o-document-plus')
                                ->color('primary')
                                ->form([
                                    FileUpload::make('key_file')
                                        ->label('Select private key file')
                                        ->disk('local')
                                        ->directory('temp_keys')
                                        ->visibility('private')
                                        ->required()
                                        ->preserveFilenames()
                                        ->helperText('Select your id_rsa, id_ed25519, etc. from your computer.'),
                                ])
                                ->action(function (array $data, Get $get, Set $set): void {
                                    if (blank($data['key_file'] ?? null)) {
                                        return;
                                    }

                                    $path = Storage::disk('local')->path($data['key_file']);
                                    $content = file_get_contents($path);

                                    $set('settings.private_key', $content);

                                    // Derive and set public key immediately
                                    $publicKey = app(SshKeyService::class)->derivePublicKey($content, (string) $get('settings.passphrase'));
                                    if ($publicKey) {
                                        $set('settings.public_key', $publicKey);
                                    }

                                    // Cleanup
                                    Storage::disk('local')->delete($data['key_file']);

                                    Notification::make()
                                        ->title('File imported')
                                        ->body('Successfully read key from file.')
                                        ->success()
                                        ->send();
                                }),
                            Action::make('generateKey')
                                ->label('Generate')
                                ->icon('heroicon-o-rocket-launch')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Generate new SSH key pair?')
                                ->modalDescription('This will replace current keys with a fresh 2048-bit RSA pair. Make sure to download or save your old ones if still needed.')
                                ->action(function (Get $get, Set $set): void {
                                    $pair = app(SshKeyService::class)->generateKeyPair('rsa', (string) $get('settings.passphrase'));
                                    $set('settings.private_key', $pair['private_key']);
                                    $set('settings.public_key', $pair['public_key']);
                                }),
                            Action::make('scanLocal')
                                ->label('Scan local')
                                ->icon('heroicon-o-magnifying-glass')
                                ->color('warning')
                                ->form([
                                    TextInput::make('search_path')
                                        ->label('Search directory')
                                        ->placeholder('/home/user/.ssh or storage/app/keys')
                                        ->helperText('The system will scan this folder (and common defaults) for identity files.')
                                        ->default(storage_path('app/ssh_keys')),
                                ])
                                ->action(function (array $data, Get $get, Set $set): void {
                                    $keys = app(SshKeyService::class)->discoverLocalKeys($data['search_path']);
                                    if (empty($keys)) {
                                        Notification::make()
                                            ->title('No keys found')
                                            ->body('Could not find any valid SSH private keys in the specified path.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    // Take the first match
                                    $path = array_key_first($keys);
                                    $privateKey = file_get_contents($path);
                                    $set('settings.private_key', $privateKey);

                                    // Derive and set public key immediately
                                    $publicKey = app(SshKeyService::class)->derivePublicKey($privateKey, (string) $get('settings.passphrase'));
                                    if ($publicKey) {
                                        $set('settings.public_key', $publicKey);
                                    }

                                    Notification::make()
                                        ->title('Key imported')
                                        ->body('Imported identity from: '.basename($path))
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),

                        Textarea::make('settings.public_key')
                            ->label('Public key (Read-only)')
                            ->helperText('Paste this into GitHub Deploy Keys or the remote authorized_keys file.')
                            ->rows(3)
                            ->readOnly()
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => 'font-mono text-xs bg-neutral-950 text-neutral-100 border-none rounded-xl p-3 ring-1 ring-white/10 shadow-inner',
                            ]),

                        Actions::make([
                            Action::make('copyPublicKey')
                                ->label('Copy to clipboard')
                                ->icon('heroicon-m-clipboard-document')
                                ->color('gray')
                                ->extraAttributes([
                                    'x-on:click' => "window.navigator.clipboard.writeText(\$wire.get('data.settings.public_key')); \$tooltip('Copied!', { timeout: 1500 });",
                                ])
                                ->action(function (): void {
                                    Notification::make()
                                        ->title('Copied!')
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('GitHub configuration')
                    ->description('Access tokens and identity for automated repository browsing.')
                    ->visible(fn (Get $get): bool => $get('type') === 'github')
                    ->schema([
                        TextInput::make('settings.username')
                            ->label('Organization / Owner')
                            ->placeholder('laravel')
                            ->required(),
                        TextInput::make('settings.repository')
                            ->label('Specific repository')
                            ->placeholder('framework')
                            ->helperText('Optional: Leave blank to allow profile to access all for this owner.'),
                        TextInput::make('settings.api_token')
                            ->label('Personal Access Token (PAT)')
                            ->placeholder('ghp_...')
                            ->password()
                            ->revealable()
                            ->dehydrated(true)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Must have "repo" or "admin:repo_hook" scopes for deployments.'),
                    ])
                    ->columns(2),

                Section::make('cPanel / WHM configuration')
                    ->description('Authentication for server-level deployments using cPanel APIs.')
                    ->visible(fn (Get $get): bool => $get('type') === 'cpanel')
                    ->schema([
                        TextInput::make('settings.username')
                            ->label('cPanel Username')
                            ->placeholder('user123')
                            ->required(),
                        TextInput::make('settings.api_port')
                            ->label('API Port')
                            ->placeholder('2083')
                            ->default('2083')
                            ->numeric()
                            ->required(),
                        TextInput::make('settings.api_token')
                            ->label('API Token')
                            ->placeholder('Token content')
                            ->password()
                            ->revealable()
                            ->dehydrated(true)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('DNS / Cloudflare configuration')
                    ->description('Identity for automatic domain and DNS zone management.')
                    ->visible(fn (Get $get): bool => $get('type') === 'dns')
                    ->schema([
                        Select::make('settings.provider')
                            ->label('DNS Provider')
                            ->options([
                                'cloudflare' => 'Cloudflare',
                                'digitalocean' => 'DigitalOcean',
                                'hetzner' => 'Hetzner',
                                'manual' => 'Manual Mode',
                            ])
                            ->default('cloudflare')
                            ->required(),
                        TextInput::make('settings.zone_id')
                            ->label('Zone ID / Name')
                            ->placeholder('32-char hex string')
                            ->required(),
                        TextInput::make('settings.api_token')
                            ->label('API Token / Secret')
                            ->password()
                            ->revealable()
                            ->dehydrated(true)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('settings.proxy_records')
                            ->label('Enable Proxy (Cloudflare Only)')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Webhook configuration')
                    ->description('Security settings for inbound/outbound deployment webhooks.')
                    ->visible(fn (Get $get): bool => $get('type') === 'webhook')
                    ->schema([
                        TextInput::make('settings.webhook_url')
                            ->label('Endpoint URL')
                            ->url()
                            ->placeholder('https://deploy.example.com/hook')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('settings.secret')
                            ->label('Signing Secret')
                            ->password()
                            ->revealable()
                            ->dehydrated(true)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Used to verify payload authenticity via HMAC signatures.'),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $type): array
    {
        return match ($type) {
            'ssh' => [
                'username' => 'root',
                'port' => '22',
                'private_key' => '',
                'public_key' => '',
                'passphrase' => '',
                'sudo_password' => '',
            ],
            'cpanel' => [
                'username' => '',
                'api_token' => '',
                'api_port' => '2083',
            ],
            'github' => [
                'api_token' => '',
                'username' => '',
                'repository' => '',
            ],
            'dns' => [
                'provider' => 'cloudflare',
                'api_token' => '',
                'zone_id' => '',
                'proxy_records' => '1',
            ],
            'webhook' => [
                'webhook_url' => '',
                'secret' => '',
            ],
            default => [],
        };
    }
}
