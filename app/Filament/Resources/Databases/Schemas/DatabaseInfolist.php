<?php

namespace App\Filament\Resources\Databases\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DatabaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Database tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => ucfirst((string) ($record->status ?? 'requested')))
                            ->badgeColor(fn ($record): string => match ($record->status ?? 'requested') {
                                'requested' => 'warning',
                                'provisioning' => 'info',
                                'provisioned' => 'success',
                                'deleted' => 'gray',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Database details')
                                    ->schema([
                                        TextEntry::make('site.name')
                                            ->label('Site'),
                                        TextEntry::make('server.name')
                                            ->label('Server'),
                                        TextEntry::make('name')
                                            ->label('Requested name')
                                            ->copyable(),
                                        TextEntry::make('username')
                                            ->label('Requested user')
                                            ->copyable(),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                'requested' => 'warning',
                                                'provisioning' => 'info',
                                                'provisioned' => 'success',
                                                'deleted' => 'gray',
                                                'failed' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('provisioned_at')
                                            ->label('Provisioned at')
                                            ->dateTime()
                                            ->placeholder('Not provisioned'),
                                        TextEntry::make('last_synced_at')
                                            ->label('Last synced')
                                            ->dateTime()
                                            ->placeholder('Never'),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('cPanel')
                            ->schema([
                                Section::make('Live identifiers')
                                    ->description('These are the names cPanel expects on the server.')
                                    ->schema([
                                        TextEntry::make('cpanelDatabaseName')
                                            ->label('cPanel database')
                                            ->state(fn ($record): string => $record->cpanelDatabaseName() ?? 'n/a'),
                                        TextEntry::make('cpanelUsername')
                                            ->label('cPanel user')
                                            ->state(fn ($record): string => $record->cpanelUsername() ?? 'n/a'),
                                        TextEntry::make('password')
                                            ->label('Password')
                                            ->state(fn ($record): string => filled($record->password) ? 'Stored securely' : 'Not set'),
                                        TextEntry::make('last_error')
                                            ->label('Last error')
                                            ->columnSpanFull()
                                            ->placeholder('None'),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->columnSpanFull()
                                            ->placeholder('No notes yet'),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
