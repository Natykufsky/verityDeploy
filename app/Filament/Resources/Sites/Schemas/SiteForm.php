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
            Tabs::make('Site configuration')
                ->columnSpanFull()
                ->persistTab()
                ->persistTabInQueryString('tab')
                ->tabs([
                    Tab::make('Overview')
                        ->badge('Base')
                        ->badgeColor('primary')
                        ->schema([
                            Section::make('Deployment source')
                                ->schema([
                                    Select::make('deploy_source')
                                        ->options([
                                            'git' => 'Git repository',
                                            'local' => 'Local machine',
                                        ])
                                        ->live()
                                        ->default(fn (): string => app(AppSettings::class)->defaultDeploySource())
                                        ->required(),
                                    TextInput::make('repository_url')
                                        ->label('Git URL')
                                        ->placeholder('e.g. git@github.com:user/repo.git')
                                        ->url()
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git'),
                                    TextInput::make('local_source_path')
                                        ->label('Local absolute path')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                                        ->columnSpanFull(),
                                    Select::make('github_credential_profile_id')
                                        ->label('GitHub profile')
                                        ->options(fn (): array => CredentialProfile::query()->ofType('github')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(fn (): ?int => app(AppSettings::class)->defaultGithubGithubCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Select auth token')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git')
                                        ->columnSpanFull(),
                                    TextInput::make('default_branch')
                                        ->label('Branch')
                                        ->required()
                                        ->default(fn (): string => app(AppSettings::class)->defaultBranch())
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git'),
                                    Toggle::make('ignore_local_source_ignored_files')
                                        ->label('Respect .gitignore')
                                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                                        ->default(true),
                                ])
                                ->columns(2),
                            Section::make('Infrastructure')
                                ->schema([
                                    Select::make('server_id')
                                        ->relationship('server', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    TextInput::make('name')
                                        ->label('App name')
                                        ->placeholder('My Awesome App')
                                        ->required(),
                                    Select::make('team_id')
                                        ->label('Owner team')
                                        ->options(app(Team::class)->query()->accessibleTo()->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable()
                                        ->placeholder('Inherit from server'),
                                    Select::make('webhook_credential_profile_id')
                                        ->label('Webhook profile')
                                        ->options(CredentialProfile::query()->ofType('webhook')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(app(AppSettings::class)->defaultWebhookCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Inherit default'),
                                    TextInput::make('deploy_path')
                                        ->label('Absolute deploy path')
                                        ->placeholder('/home/user/apps/myapp')
                                        ->required(),
                                    TextInput::make('php_version')
                                        ->label('Runtime version')
                                        ->placeholder(fn (): string => app(AppSettings::class)->defaultPhpVersion() ?? '8.3'),
                                    TextInput::make('web_root')
                                        ->label('Web root folder')
                                        ->required()
                                        ->default(fn (): string => app(AppSettings::class)->defaultWebRoot()),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make('Domains')
                        ->badge('DNS')
                        ->badgeColor('info')
                        ->schema([
                            Section::make('Authoritative mapping')
                                ->schema([
                                    Select::make('primary_domain_id')
                                        ->label('Primary domain')
                                        ->relationship('primaryDomain', 'name', fn ($query, Get $get) => $query->where('server_id', $get('server_id')))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required()
                                        ->placeholder('Select a domain from server'),
                                    Toggle::make('force_https')
                                        ->label('Automatic HTTPS redirect')
                                        ->default(false),
                                    Select::make('dns_credential_profile_id')
                                        ->label('DNS API profile')
                                        ->options(CredentialProfile::query()->ofType('dns')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(app(AppSettings::class)->defaultDnsCredentialProfileId())
                                        ->searchable()
                                        ->placeholder('Inherit default')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make('Runtime')
                        ->badge('Env')
                        ->badgeColor('success')
                        ->schema([
                            Section::make('Environment / Shared files')
                                ->schema([
                                    Textarea::make('shared_env_contents')
                                        ->label('Full .env override')
                                        ->rows(10)
                                        ->columnSpanFull()
                                        ->placeholder('Paste full environment file contents here for manual control...'),
                                    KeyValue::make('environment_variables')
                                        ->label('Individual variables')
                                        ->keyLabel('Variable')
                                        ->valueLabel('Value')
                                        ->addActionLabel('Add entry')
                                        ->columnSpanFull(),
                                    View::make('filament.sites.environment-preview')
                                        ->columnSpanFull()
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => SiteEnvironmentPreview::build(
                                                (array) ($get('environment_variables') ?? []),
                                                $get('shared_env_contents'),
                                            ),
                                        ]),
                                    Repeater::make('shared_files')
                                        ->label('Persistent shared files')
                                        ->schema([
                                            TextInput::make('path')
                                                ->required()
                                                ->placeholder('storage/app/public/...'),
                                            Textarea::make('contents')
                                                ->rows(5)
                                                ->columnSpanFull()
                                                ->placeholder('Enter persistent file contents...'),
                                        ])
                                        ->addActionLabel('Add file')
                                        ->itemLabel(fn (array $state): string => filled($state['path'] ?? null) ? (string) $state['path'] : 'Persistent file')
                                        ->columnSpanFull()
                                        ->collapsible(),
                                ])
                                ->columns(1),
                        ]),
                    Tab::make('Audit')
                        ->badge('Lifecycle')
                        ->badgeColor('gray')
                        ->schema([
                            Toggle::make('active')
                                ->label('Site is globally active')
                                ->default(true),
                            DateTimePicker::make('last_deployed_at')
                                ->label('Last deployment timestamp')
                                ->disabled(),
                            Textarea::make('notes')
                                ->label('Administrative notes')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Tab::make('Architecture')
                        ->badge('Spec')
                        ->badgeColor('gray')
                        ->schema([
                            Section::make('Infrastructure visualization')
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
