<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Services\ServerMetrics\ServerMetricsBridgeUrl;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ServerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Server tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => ucfirst((string) $record->status))
                            ->badgeColor(fn ($record): string => match ($record->status) {
                                'online' => 'success',
                                'offline' => 'gray',
                                'error' => 'danger',
                                default => 'warning',
                            })
                            ->schema([
                                Section::make('Server overview')
                                    ->schema([
                                        View::make('filament.servers.server-overview')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Core details')
                                    ->schema([
                                        TextEntry::make('owner.name')
                                            ->label('Owner')
                                            ->placeholder('Unassigned'),
                                        TextEntry::make('team.name')
                                            ->label('Team')
                                            ->placeholder('No team assigned'),
                                        TextEntry::make('ip_address')
                                            ->label('IP address')
                                            ->copyable(),
                                        TextEntry::make('ssh_user')
                                            ->label('SSH user'),
                                        TextEntry::make('ssh_port')
                                            ->label('SSH port'),
                                        TextEntry::make('cpanel_username')
                                            ->label('cPanel username')
                                            ->placeholder('Same as SSH user if not set')
                                            ->copyable(),
                                        TextEntry::make('provider_reference')
                                            ->label('Provider reference')
                                            ->copyable(),
                                        TextEntry::make('provider_region')
                                            ->label('Provider region'),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('Metrics')
                            ->badge('Live')
                            ->badgeColor('success')
                            ->schema([
                                Section::make('Health metrics')
                                    ->schema([
                                        View::make('filament.servers.server-metrics')
                                            ->viewData(fn ($record): array => [
                                                'record' => $record->fresh(),
                                                'bridge' => app(ServerMetricsBridgeUrl::class)->make($record->fresh()),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('History')
                            ->badge(fn ($record): string => (string) count($record->connectionTests))
                            ->badgeColor('primary')
                            ->schema([
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
                            ]),
                    ]),
            ]);
    }
}
