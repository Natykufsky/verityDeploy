<?php

namespace App\Filament\Resources\Databases\Schemas;

use App\Models\Database;
use App\Models\Site;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DatabaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Linked site')
                    ->description('Choose the site this database belongs to.')
                    ->schema([
                        Select::make('site_id')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Placeholder::make('cpanel_preview')
                            ->label('cPanel identifiers')
                            ->content(function (Get $get): string {
                                $site = filled($get('site_id')) ? Site::query()->with('server')->find($get('site_id')) : null;

                                if (! $site || ! $site->server) {
                                    return 'Select a site to preview the cPanel database and user names.';
                                }

                                $database = new Database([
                                    'name' => (string) ($get('name') ?: $site->name),
                                    'username' => (string) ($get('username') ?: $site->name),
                                ]);
                                $database->setRelation('server', $site->server);

                                return sprintf(
                                    'Database: %s | User: %s',
                                    $database->cpanelDatabaseName() ?? 'n/a',
                                    $database->cpanelUsername() ?? 'n/a',
                                );
                            }),
                    ])
                    ->columns(['lg' => 2]),

                Section::make('Database details')
                    ->description('Track the database name, login user, and provisioning state.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Database name')
                            ->placeholder('e.g. myapp_prod')
                            ->required()
                            ->live(),
                        TextInput::make('username')
                            ->label('Database user')
                            ->placeholder('Defaults to the database name'),
                        TextInput::make('password')
                            ->label('Database password')
                            ->password()
                            ->revealable()
                            ->placeholder('Leave blank to auto-generate')
                            ->helperText('The password is encrypted at rest and used when cPanel provisioning runs.'),
                        Select::make('status')
                            ->options([
                                'requested' => 'Requested',
                                'provisioning' => 'Provisioning',
                                'provisioned' => 'Provisioned',
                                'failed' => 'Failed',
                                'deleted' => 'Deleted',
                            ])
                            ->default('requested')
                            ->required(),
                    ])
                    ->columns(['lg' => 2]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
