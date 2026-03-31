<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class TeamInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('How this team page works')
                    ->schema([
                        View::make('filament.teams.team-guide'),
                    ]),
                Section::make('Team overview')
                    ->schema([
                        View::make('filament.teams.team-summary'),
                    ]),
            ]);
    }
}
