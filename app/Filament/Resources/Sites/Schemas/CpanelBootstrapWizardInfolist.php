<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class CpanelBootstrapWizardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Snapshot')
                    ->schema([
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('name'),
                        TextEntry::make('deploy_source')
                            ->badge(),
                        TextEntry::make('deploy_path')
                            ->copyable(),
                        TextEntry::make('current_release_path')
                            ->label('Current release')
                            ->copyable(),
                        TextEntry::make('last_deployed_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Wizard Output')
                    ->schema([
                        View::make('filament.sites.cpanel-bootstrap-wizard'),
                    ]),
            ]);
    }
}
