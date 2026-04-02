<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
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
                ])->columns(['md' => 2]),
        ];

        $sourceFields = [
            Section::make('Deployment Source')
                ->description('Specify where your code lives and how to pull it.')
                ->icon('heroicon-o-cloud-arrow-down')
                ->schema([
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
                    TextInput::make('local_source_path')
                        ->label('Local absolute path')
                        ->placeholder('C:\Apps\MySite')
                        ->visible(fn (Get $get): bool => $get('deploy_source') === 'local')
                        ->columnSpanFull(),
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
            Section::make('Path Configuration')
                ->description('Where the files will be placed on the remote disk.')
                ->icon('heroicon-o-folder-open')
                ->schema([
                    TextInput::make('deploy_path')
                        ->label('Absolute Deploy Path')
                        ->placeholder('/home/user/apps/myapp')
                        ->required(),
                    TextInput::make('web_root')
                        ->label('Web Root Folder')
                        ->required()
                        ->default('public'),
                ])->columns(['md' => 2]),
            Section::make('Domain Mapping')
                ->description('Web server configuration for inbound traffic.')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    Select::make('primary_domain_id')
                        ->label('Primary Domain')
                        ->relationship('primaryDomain', 'name', fn ($query, Get $get) => $query->where('server_id', $get('server_id')))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Toggle::make('force_https')
                        ->label('Force HTTPS')
                        ->default(true),
                    TextInput::make('health_check_endpoint')
                        ->label('Health Check Path')
                        ->placeholder('/api/health')
                        ->helperText('Endpoint to probe after deployment to verify site is up.'),
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
        $site = new Site([
            'primary_domain_id' => $get('primary_domain_id'),
            'force_https' => (bool) $get('force_https'),
            'deploy_path' => $get('deploy_path'),
            'web_root' => $get('web_root'),
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
