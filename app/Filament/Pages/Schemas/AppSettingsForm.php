<?php

namespace App\Filament\Pages\Schemas;

use App\Models\CredentialProfile;
use App\Services\AppSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class AppSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        $settings = app(AppSettings::class);

        return $schema
            ->components([
                Tabs::make('App settings')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('General')
                            ->badge('Base')
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Branding')
                                    ->extraAttributes(['id' => 'branding-settings'])
                                    ->schema([
                                        TextInput::make('app_name')
                                            ->required()
                                            ->maxLength(120)
                                            ->columnSpanFull(),
                                        FileUpload::make('app_logo_path')
                                            ->label('App logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->preserveFilenames()
                                            ->columnSpanFull()
                                            ->helperText('Upload the primary app logo used in the panel header and brand surfaces.'),
                                        FileUpload::make('app_favicon_path')
                                            ->label('Favicon')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->preserveFilenames()
                                            ->columnSpanFull()
                                            ->helperText('Upload a square icon used as the browser favicon.'),
                                        TextInput::make('app_tagline')
                                            ->label('App tagline')
                                            ->placeholder('Deploy apps with confidence')
                                            ->columnSpanFull()
                                            ->helperText('Short brand line shown in the settings page and future marketing surfaces.'),
                                        Textarea::make('app_description')
                                            ->label('App description')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->helperText('A longer description of what VerityDeploy does and how it should be presented.'),
                                        TextInput::make('app_support_url')
                                            ->label('Support URL')
                                            ->url()
                                            ->placeholder('https://support.example.com')
                                            ->columnSpanFull()
                                            ->helperText('Optional support or help link for footer and login surface references.'),
                                        View::make('filament.pages.app-settings-branding-preview')
                                            ->columnSpanFull()
                                            ->viewData(fn (Get $get): array => [
                                                'appName' => filled($get('app_name')) ? (string) $get('app_name') : app(AppSettings::class)->appName(),
                                                'logoUrl' => filled($get('app_logo_path')) ? Storage::disk('public')->url((string) $get('app_logo_path')) : app(AppSettings::class)->brandLogoUrl(),
                                                'faviconUrl' => filled($get('app_favicon_path')) ? Storage::disk('public')->url((string) $get('app_favicon_path')) : app(AppSettings::class)->faviconUrl(),
                                                'tagline' => filled($get('app_tagline')) ? (string) $get('app_tagline') : app(AppSettings::class)->appTagline(),
                                                'description' => filled($get('app_description')) ? (string) $get('app_description') : app(AppSettings::class)->appDescription(),
                                                'supportUrl' => filled($get('app_support_url')) ? (string) $get('app_support_url') : app(AppSettings::class)->appSupportUrl(),
                                            ]),
                                    ])
                                    ->columns(1),
                                Section::make('Deployment Defaults')
                                    ->extraAttributes(['id' => 'deployment-defaults'])
                                    ->schema([
                                        Select::make('default_deploy_source')
                                            ->options([
                                                'git' => 'Git',
                                                'local' => 'Local',
                                            ])
                                            ->required(),
                                        TextInput::make('default_branch')
                                            ->required(),
                                        TextInput::make('default_web_root')
                                            ->required(),
                                        TextInput::make('default_php_version')
                                            ->placeholder('8.3'),
                                        TextInput::make('default_ssh_port')
                                            ->numeric()
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Credentials')
                            ->badge('Shared')
                            ->badgeColor('warning')
                            ->schema([
                                Section::make('Profile defaults')
                                    ->extraAttributes(['id' => 'credential-defaults'])
                                    ->schema([
                                        Select::make('default_ssh_credential_profile_id')
                                            ->label('Default SSH profile')
                                            ->options(fn (): array => CredentialProfile::query()->ofType('ssh')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->placeholder('None selected')
                                            ->helperText('Select the shared SSH profile that new servers should inherit by default.'),
                                        Select::make('default_cpanel_credential_profile_id')
                                            ->label('Default cPanel profile')
                                            ->options(fn (): array => CredentialProfile::query()->ofType('cpanel')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->placeholder('None selected')
                                            ->helperText('Select the shared cPanel profile that new servers should inherit by default.'),
                                        Select::make('default_dns_credential_profile_id')
                                            ->label('Default DNS profile')
                                            ->options(fn (): array => CredentialProfile::query()->ofType('dns')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->placeholder('None selected')
                                            ->helperText('Select the shared DNS profile that new servers or sites should inherit by default.'),
                                        Select::make('default_webhook_credential_profile_id')
                                            ->label('Default webhook profile')
                                            ->options(fn (): array => CredentialProfile::query()->ofType('webhook')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->placeholder('None selected')
                                            ->helperText('Select the shared webhook profile that new site webhook settings should inherit by default.'),
                                    ])
                                    ->columns(2),

                            ]),
                        Tab::make('GitHub')
                            ->badge('GitHub')
                            ->badgeColor('info')
                            ->schema([
                                Section::make('Profile defaults')
                                    ->extraAttributes(['id' => 'github-defaults'])
                                    ->schema([
                                        Select::make('default_github_credential_profile_id')
                                            ->label('Default GitHub profile')
                                            ->options(fn (): array => CredentialProfile::query()->ofType('github')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->placeholder('None selected')
                                            ->helperText('Select the shared GitHub profile new integrations should inherit.'),
                                    ])
                                    ->columns(1),
                                Section::make('Webhook defaults')
                                    ->extraAttributes(['id' => 'webhook-defaults'])
                                    ->schema([
                                        TextInput::make('github_webhook_path')
                                            ->required()
                                            ->helperText('The endpoint GitHub should call when push events happen.')
                                            ->columnSpanFull(),
                                        TextInput::make('github_webhook_events')
                                            ->required()
                                            ->helperText('Comma-separated GitHub events to subscribe to, such as push.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                                Section::make('GitHub integration')
                                    ->extraAttributes(['id' => 'github-integration'])
                                    ->schema([

                                        TextInput::make('github_oauth_client_id')
                                            ->label('GitHub OAuth client ID')
                                            ->helperText('Used to connect GitHub without a PAT.')
                                            ->columnSpanFull(),
                                        TextInput::make('github_oauth_client_secret')
                                            ->label('GitHub OAuth client secret')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Stored encrypted. Leave blank to keep the saved secret.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                        Tab::make('Alerts')
                            ->badge('Notify')
                            ->badgeColor('success')
                            ->schema([
                                Section::make('Alert delivery')
                                    ->extraAttributes(['id' => 'alert-delivery'])
                                    ->schema([
                                        Toggle::make('alert_email_enabled')
                                            ->label('Email alerts')
                                            ->helperText('When enabled, operational alerts are emailed to users who can access the Filament panel.'),
                                        Toggle::make('alert_webhooks_enabled')
                                            ->label('Webhook alerts')
                                            ->helperText('When enabled, operational alerts are posted to each URL below as JSON payloads.'),
                                        Textarea::make('alert_webhook_urls')
                                            ->label('Webhook URLs')
                                            ->rows(4)
                                            ->placeholder("https://example.com/webhooks/veritydeploy\nhttps://backup.example.com/alerts")
                                            ->helperText('Enter one endpoint per line. Leave blank to disable webhook delivery.')
                                            ->columnSpanFull(),
                                        TextInput::make('alert_webhook_secret')
                                            ->label('Webhook signing secret')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Optional shared secret used to sign outbound webhook payloads.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                    ]),
            ]);
    }
}
