<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Services\AppSettings;
use App\Support\SiteDomainPreview;
use App\Support\SiteEnvironmentPreview;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
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

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Site form')
                ->columnSpanFull()
                ->persistTab()
                ->persistTabInQueryString('tab')
                ->tabs([
                    Tab::make('Overview')
                        ->badge('Base')
                        ->badgeColor('primary')
                        ->schema([
                            Section::make('Site details')
                                ->schema([
                                    Select::make('server_id')
                                        ->relationship('server', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    TextInput::make('name')
                                        ->required(),
                                    Select::make('team_id')
                                        ->label('Team')
                                        ->options(app(Team::class)->query()->accessibleTo()->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable()
                                        ->placeholder('Inherit from server')
                                        ->helperText('Leave blank to inherit the team from the assigned server.'),
                                    TextInput::make('repository_url')
                                        ->url()
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git'),
                                    Select::make('github_credential_profile_id')
                                        ->label('GitHub credential profile')
                                        ->options(fn (): array => CredentialProfile::query()->ofType('github')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(fn (): ?int => app(AppSettings::class)->defaultGithubCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Use default or leave blank')
                                        ->helperText('Select the shared GitHub profile that this site should use for repository access and webhook setup.')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git')
                                        ->columnSpanFull(),
                                    Select::make('webhook_credential_profile_id')
                                        ->label('Webhook credential profile')
                                        ->options(CredentialProfile::query()->ofType('webhook')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(app(AppSettings::class)->defaultWebhookCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Use default or leave blank')
                                        ->helperText('Select the shared webhook profile that should be used for inbound deployment and alert hooks.')
                                        ->columnSpanFull(),
                                    TextInput::make('default_branch')
                                        ->required()
                                        ->default(fn (): string => app(AppSettings::class)->defaultBranch())
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git'),
                                    TextInput::make('deploy_path')
                                        ->required(),
                                    TextInput::make('php_version')
                                        ->placeholder(fn (): string => app(AppSettings::class)->defaultPhpVersion() ?? '8.3'),
                                    TextInput::make('web_root')
                                        ->required()
                                        ->default(fn (): string => app(AppSettings::class)->defaultWebRoot()),
                                    Select::make('deploy_source')
                                        ->options([
                                            'git' => 'Git',
                                            'local' => 'Local',
                                        ])
                                        ->live()
                                        ->default(fn (): string => app(AppSettings::class)->defaultDeploySource())
                                        ->required(),
                                    TextInput::make('local_source_path')
                                        ->label('Local source path')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                                        ->helperText('Used when the dashboard server packages a local codebase and uploads it to the target server.')
                                        ->columnSpanFull(),
                                    Toggle::make('ignore_local_source_ignored_files')
                                        ->label('Ignore .gitignored files and folders')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                                        ->default(true)
                                        ->helperText('Keep this on to match Git-style deploys. Turn it off if you want local-source deploys to include ignored files and folders.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                            Section::make('Lifecycle')
                                ->schema([
                                    Toggle::make('active')
                                        ->default(true),
                                    DateTimePicker::make('last_deployed_at'),
                                    Textarea::make('notes')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make('Domains')
                        ->badge('Map')
                        ->badgeColor('info')
                        ->schema([
                            Section::make('Domain mapping')
                                ->schema([
                                    Select::make('primary_domain_id')
                                        ->label('Primary domain')
                                        ->relationship('primaryDomain', 'name', fn ($query, Get $get) => $query->where('server_id', $get('server_id')))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required()
                                        ->placeholder('Select a domain from the assigned server')
                                        ->helperText('Manage domains globally under the Server resource.'),
                                    Toggle::make('force_https')
                                        ->label('Force HTTPS')
                                        ->default(false)
                                        ->helperText('Redirect HTTP traffic to HTTPS once the certificate is ready.'),
                                ])
                                ->columns(1),
                            Section::make('SSL and Routing')
                                ->schema([
                                    Select::make('dns_credential_profile_id')
                                        ->label('DNS credential profile')
                                        ->options(CredentialProfile::query()->ofType('dns')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(app(AppSettings::class)->defaultDnsCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Use default or leave blank')
                                        ->helperText('Select the shared DNS profile that should be used for domain and record management.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(1),
                        ]),
                    Tab::make('Runtime')
                        ->badge('Env')
                        ->badgeColor('success')
                        ->schema([
                            Section::make('Runtime configuration')
                                ->schema([
                                    Textarea::make('shared_env_contents')
                                        ->label('Shared .env file')
                                        ->rows(12)
                                        ->columnSpanFull()
                                        ->helperText('Paste the exact contents of the shared .env file here when you need full control. Leave this blank to generate the .env file from the environment variables below.'),
                                    KeyValue::make('environment_variables')
                                        ->label('Environment variables')
                                        ->keyLabel('Variable')
                                        ->valueLabel('Value')
                                        ->keyPlaceholder('APP_ENV')
                                        ->valuePlaceholder('production')
                                        ->addActionLabel('Add variable')
                                        ->columnSpanFull()
                                        ->helperText('These values are written to the shared .env file only when the custom .env override is blank.'),
                                    View::make('filament.sites.environment-preview')
                                        ->columnSpanFull()
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => SiteEnvironmentPreview::build(
                                                (array) ($get('environment_variables') ?? []),
                                                $get('shared_env_contents'),
                                            ),
                                        ]),
                                    Repeater::make('shared_files')
                                        ->label('Shared files')
                                        ->schema([
                                            TextInput::make('path')
                                                ->required()
                                                ->placeholder('storage/app/public/.gitkeep'),
                                            Textarea::make('contents')
                                                ->rows(8)
                                                ->columnSpanFull()
                                                ->placeholder('File contents that should persist between releases.'),
                                        ])
                                        ->addActionLabel('Add shared file')
                                        ->itemLabel(fn (array $state): string => filled($state['path'] ?? null) ? (string) $state['path'] : 'Shared file')
                                        ->columnSpanFull()
                                        ->collapsible(),
                                ])
                                ->columns(1),
                        ]),
                    Tab::make('Previews')
                        ->badge('Live')
                        ->badgeColor('gray')
                        ->schema([
                            Section::make('Deployment previews')
                                ->schema([
                                    View::make('filament.sites.domain-preview')
                                        ->columnSpanFull()
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => SiteDomainPreview::build(
                                                $get('primary_domain_id'),
                                                [],
                                                [],
                                                null,
                                                $get('deploy_path'),
                                                $get('web_root'),
                                                'valid',
                                                (bool) $get('force_https'),
                                            ),
                                        ]),
                                    View::make('filament.sites.vhost-preview')
                                        ->columnSpanFull()
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => self::sitePreview($get)->vhost_preview,
                                        ]),
                                    View::make('filament.sites.dns-preview')
                                        ->columnSpanFull()
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => self::sitePreview($get)->dns_preview,
                                        ]),
                                ])
                                ->columns(1),
                        ]),
                ]),
        ]);
    }

    protected static function sitePreview(Get $get): Site
    {
        $site = new Site([
            'primary_domain_id' => $get('primary_domain_id'),
            'force_https' => (bool) $get('force_https'),
            'deploy_path' => $get('deploy_path'),
            'web_root' => $get('web_root'),
            'current_release_path' => $get('current_release_path'),
        ]);

        if (filled($get('server_id'))) {
            $server = Server::query()->find($get('server_id'));

            if ($server) {
                $site->setRelation('server', $server);
            }
        }

        return $site;
    }
}
