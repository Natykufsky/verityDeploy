<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Set;
use App\Models\CredentialProfile;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Services\Servers\ServerDomainSynchronizer;
use App\Services\AppSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        $isCreatePage = $schema->getOperation() === 'create';

        $identityFields = [
            Section::make('Core Details')
                ->description('Link your app to a target server and team.')
                ->icon('heroicon-o-finger-print')
                ->schema([
                    Select::make('server_id')
                        ->relationship('server', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->disabled(! $isCreatePage), // Prevent changing server after creation for stability
                    TextInput::make('name')
                        ->label('App Name')
                        ->placeholder('e.g. VerityAPI')
                        ->required(),
                    Select::make('team_id')
                        ->label('Owner Team')
                        ->options(Team::query()->accessibleTo()->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('Inherit from server'),
                    Select::make('environment')
                        ->label('Environment')
                        ->options([
                            'production' => 'Production',
                            'staging' => 'Staging',
                            'development' => 'Development',
                        ])
                        ->default('production')
                        ->required(),
                ])->columns(['md' => 2]),
        ];

        $sourceFields = [
            Section::make('Deployment Source')
                ->description('Specify where your code lives and how to pull it.')
                ->icon('heroicon-o-cloud-arrow-down')
                ->schema([
                    Select::make('project_type')
                        ->label('Project Template')
                        ->options([
                            'laravel' => 'Laravel',
                            'symfony' => 'Symfony',
                            'nodejs' => 'Node.js',
                            'python' => 'Python (Django, Flask)',
                            'static' => 'Static Site',
                            'custom' => 'Custom PHP',
                        ])
                        ->live()
                        ->default('laravel')
                        ->required(),
                    Select::make('deploy_source')
                        ->options([
                            'git' => 'Git Repository',
                            'local' => 'Local Machine',
                        ])
                        ->live()
                        ->default('local')
                        ->required(),
                    TextInput::make('repository_url')
                        ->label('Git URL')
                        ->placeholder('git@github.com:user/repo.git')
                        ->url()
                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git'),
                    \Filament\Forms\Components\FileUpload::make('local_source_archive')
                        ->label('Upload Local Source')
                        ->directory(true)
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize(1024 * 100) // 100MB
                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                        ->columnSpanFull()
                        ->helperText('Select a directory or ZIP file from your local machine to upload as the source.'),
                    Select::make('github_credential_profile_id')
                        ->label('GitHub Profile')
                        ->options(CredentialProfile::query()->ofType('github')->where('is_active', true)->pluck('name', 'id')->all())
                        ->default(fn (): ?int => app(AppSettings::class)->defaultGithubCredentialProfileId())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'git')
                        ->columnSpanFull(),
                ])->columns(['md' => 2]),
        ];

        $configFields = [
            Section::make('Domain Mapping')
                ->description('Web server configuration for inbound traffic.')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    Select::make('primary_domain_id')
                        ->label('Primary Domain')
                        ->options(function (callable $get) {
                            $serverId = $get('server_id');
                            if (!$serverId) return [];

                            $server = Server::find($serverId);
                            if (!$server) return [];

                            $synchronizer = app(ServerDomainSynchronizer::class);
                            $preview = $synchronizer->preview($server);

                            return collect($preview['domains'] ?? [])
                                ->pluck('domain', 'domain')
                                ->toArray();
                        })
                        ->searchable()
                        ->live()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Domain Name')
                                ->required()
                                ->unique(ignoreRecord: true),
                            Select::make('type')
                                ->label('Domain Type')
                                ->options([
                                    'primary' => 'Primary Domain',
                                    'addon' => 'Addon Domain',
                                    'subdomain' => 'Subdomain',
                                ])
                                ->default('addon')
                                ->required(),
                            TextInput::make('web_root')
                                ->label('Document Root')
                                ->placeholder('/public_html/example.com')
                                ->helperText('The directory where this domain\'s files are served from.'),
                            TextInput::make('server_id')
                                ->hidden()
                                ->default(fn (Get $get) => $get('../../server_id')),
                        ])
                        ->helperText('Domains are loaded directly from the selected server.'),
                    TextInput::make('generated_deploy_path')
                        ->label('Generated Deploy Path')
                        ->default(function (Get $get): string {
                            $server = filled($get('server_id')) ? Server::query()->find($get('server_id')) : null;
                            $primaryDomain = filled($get('primary_domain_id')) ? Domain::query()->find($get('primary_domain_id')) : null;

                            if (! $server || ! $primaryDomain) {
                                return 'Select a server and primary domain to preview the deployment path.';
                            }

                            return Site::deriveDeployPathFromDomain($server, $primaryDomain->name)
                                ?? 'Unable to generate a deploy path for the selected domain.';
                        })
                        ->disabled()
                        ->columnSpanFull(),
                    Toggle::make('force_https')
                        ->label('Force HTTPS')
                        ->default(true),
                    Toggle::make('auto_ssl')
                        ->label('Auto SSL Certificate')
                        ->default(true)
                        ->helperText('Automatically provision and renew SSL certificates.'),
                    TextInput::make('health_check_endpoint')
                        ->label('Health Check Path')
                        ->placeholder('/api/health')
                        ->helperText('Endpoint to probe after deployment to verify site is up.'),
                    Select::make('php_version')
                        ->label('PHP Version')
                        ->options([
                            '8.3' => 'PHP 8.3',
                            '8.2' => 'PHP 8.2',
                            '8.1' => 'PHP 8.1',
                            '8.0' => 'PHP 8.0',
                            '7.4' => 'PHP 7.4',
                        ])
                        ->default('8.3')
                        ->required(),
                    TextInput::make('web_root')
                        ->label('Web Directory')
                        ->placeholder(fn (Get $get): string => match ($get('project_type')) {
                            'laravel' => 'public',
                            'symfony' => 'public',
                            'nodejs' => 'dist',
                            'python' => 'static',
                            'static' => 'dist',
                            'custom' => 'public',
                            default => 'public',
                        })
                        ->default(fn (Get $get): string => match ($get('project_type')) {
                            'laravel' => 'public',
                            'symfony' => 'public',
                            'nodejs' => 'dist',
                            'python' => 'static',
                            'static' => 'dist',
                            'custom' => 'public',
                            default => 'public',
                        })
                        ->required(),
                    TextInput::make('build_command')
                        ->label('Build Command')
                        ->placeholder(fn (Get $get): string => match ($get('project_type')) {
                            'nodejs' => 'npm run build',
                            'python' => 'pip install -r requirements.txt',
                            default => '',
                        })
                        ->visible(fn (Get $get): bool => in_array($get('project_type'), ['nodejs', 'python']))
                        ->helperText('Command to build the project before deployment.'),
                    TextInput::make('start_command')
                        ->label('Start Command')
                        ->placeholder(fn (Get $get): string => match ($get('project_type')) {
                            'nodejs' => 'npm start',
                            'python' => 'python app.py',
                            default => '',
                        })
                        ->visible(fn (Get $get): bool => in_array($get('project_type'), ['nodejs', 'python']))
                        ->helperText('Command to start the application.'),
                    TextInput::make('port')
                        ->label('Port')
                        ->placeholder('3000')
                        ->visible(fn (Get $get): bool => in_array($get('project_type'), ['nodejs', 'python']))
                        ->helperText('Port the application runs on.'),
                ])->columns(['md' => 2]),
        ];

        $runtimeFields = [
            Section::make('Environment Variables')
                ->description('Secrets and configurations applied at runtime.')
                ->icon('heroicon-o-variable')
                ->schema([
                    Textarea::make('shared_env_contents')
                        ->label('Static .env content')
                        ->rows(6)
                        ->columnSpanFull(),
                    KeyValue::make('environment_variables')
                        ->label('Dynamic Variables')
                        ->columnSpanFull(),
                ]),
            Section::make('Database Setup')
                ->description('Optionally create a database for this site.')
                ->icon('heroicon-o-circle-stack')
                ->schema([
                    Toggle::make('create_database')
                        ->label('Create database')
                        ->default(false)
                        ->live(),
                    TextInput::make('database_name')
                        ->label('Database Name')
                        ->placeholder('e.g. myapp_prod')
                        ->visible(fn (Get $get): bool => $get('create_database'))
                        ->required(fn (Get $get): bool => $get('create_database')),
                ]),
            Section::make('Shared Files')
                ->description('Persistent files that should be symlinked or kept across releases.')
                ->icon('heroicon-o-document-duplicate')
                ->schema([
                    Repeater::make('shared_files')
                        ->schema([
                            TextInput::make('path')->required()->placeholder('e.g. storage/database.sqlite'),
                            Textarea::make('contents')->rows(4)->columnSpanFull()->placeholder('Initial file content...'),
                        ])->columnSpanFull(),
                ]),
        ];

        return $schema->components([
            Wizard::make([
                Step::make('Identity')
                    ->key('site-step-identity')
                    ->description('Basic info')
                    ->schema($identityFields),
                Step::make('Source')
                    ->key('site-step-source')
                    ->description('Code location')
                    ->schema($sourceFields),
                Step::make('Config')
                    ->key('site-step-config')
                    ->description('Paths & Domains')
                    ->schema($configFields),
                Step::make('Runtime')
                    ->key('site-step-runtime')
                    ->description('Environment')
                    ->schema($runtimeFields),
                Step::make('Ready')
                    ->key('site-step-ready')
                    ->description($isCreatePage ? 'Finalize & Launch' : 'Review & Update')
                    ->schema([
                        Section::make($isCreatePage ? 'Launch Summary' : 'Change History')
                            ->schema([
                                View::make('filament.sites.creation-summary'),
                                Toggle::make('active')
                                    ->label('Site is active and receiving traffic')
                                    ->default(true),
                                Toggle::make('deploy_after_create')
                                    ->label('Deploy immediately after creation')
                                    ->default(true)
                                    ->helperText('Start the first deployment right after the site is created.'),
                                DateTimePicker::make('last_deployed_at')
                                    ->label('Last deployment run')
                                    ->visible(! $isCreatePage)
                                    ->disabled(),
                            ]),
                    ]),
            ])
                ->key('site-creation-wizard')
                ->columnSpanFull()
                ->persistStepInQueryString('step'),
        ]);
    }

    protected static function sitePreview(Get $get): Site
    {
        $server = null;

        if (filled($get('server_id'))) {
            $server = Server::query()->find($get('server_id'));
        }

        $primaryDomain = null;

        if (filled($get('primary_domain_id'))) {
            $primaryDomain = Domain::query()->find($get('primary_domain_id'));
        }

        $site = new Site([
            'primary_domain_id' => $get('primary_domain_id'),
            'force_https' => (bool) $get('force_https'),
            'deploy_path' => $server && $primaryDomain ? Site::deriveDeployPathFromDomain($server, $primaryDomain->name) : null,
            'web_root' => 'public',
        ]);

        if ($server) {
            $site->setRelation('server', $server);
        }

        if ($primaryDomain) {
            $site->setRelation('primaryDomain', $primaryDomain);
        }

        return $site;
    }
}
