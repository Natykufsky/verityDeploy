<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Models\Team;
use App\Services\AppSettings;
use App\Support\SiteEnvironmentPreview;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            ->options(fn (): array => Team::query()
                                ->accessibleTo()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('Inherit from server')
                            ->helperText('Leave blank to inherit the team from the assigned server.'),
                        TextInput::make('repository_url')
                            ->url(),
                        TextInput::make('default_branch')
                            ->required()
                            ->default(fn (): string => app(AppSettings::class)->defaultBranch()),
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
                    ])
                    ->columns(2),
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
                Section::make('Security and lifecycle')
                    ->schema([
                        TextInput::make('webhook_secret')
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                        Toggle::make('active')
                            ->default(true),
                        DateTimePicker::make('last_deployed_at'),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
