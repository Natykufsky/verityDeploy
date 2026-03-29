<?php

namespace App\Filament\Resources\Servers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class CpanelConnectionWizardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection Snapshot')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('connection_type')
                            ->badge(),
                        TextEntry::make('ssh_user')
                            ->label('SSH user')
                            ->copyable(),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->copyable(),
                        TextEntry::make('ssh_port')
                            ->label('SSH port'),
                        TextEntry::make('cpanel_api_port')
                            ->label('cPanel API port'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'online' => 'success',
                                'offline' => 'gray',
                                'error' => 'danger',
                                default => 'warning',
                            }),
                        TextEntry::make('last_connected_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Wizard Output')
                    ->schema([
                        View::make('filament.servers.cpanel-connection-wizard'),
                    ]),
            ]);
    }
}
