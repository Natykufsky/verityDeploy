<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Services\ServerMetrics\ServerMetricsBridgeUrl;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ServerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server overview')
                    ->schema([
                        View::make('filament.servers.server-overview')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Server details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('connection_type')
                            ->badge(),
                        TextEntry::make('ssh_user')
                            ->label('SSH user'),
                        TextEntry::make('ssh_port')
                            ->label('SSH port'),
                        TextEntry::make('provider_summary')
                            ->label('Provider summary')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
                Section::make('Health metrics')
                    ->schema([
                        View::make('filament.servers.server-metrics')
                            ->viewData(fn ($record): array => [
                                'record' => $record->fresh(),
                                'bridge' => app(ServerMetricsBridgeUrl::class)->make($record->fresh()),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Operational timeline')
                    ->schema([
                        View::make('filament.schemas.components.operational-timeline'),
                    ]),
                Section::make('Recent connection checks')
                    ->schema([
                        View::make('filament.schemas.components.connection-tests')
                            ->viewData(fn ($record): array => [
                                'record' => $record->load([
                                    'connectionTests' => fn ($query) => $query->latest('tested_at')->latest()->limit(5),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
