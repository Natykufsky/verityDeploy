<?php

namespace App\Filament\Resources\SiteBackups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SiteBackupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Backup tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => ucfirst((string) ($record->status ?? 'unknown')))
                            ->badgeColor(fn ($record): string => match ($record->status ?? 'unknown') {
                                'successful' => 'success',
                                'running' => 'info',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Backup details')
                                    ->schema([
                                        TextEntry::make('site.name')->label('Site'),
                                        TextEntry::make('operation')->badge(),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('label')->placeholder('No label'),
                                        TextEntry::make('started_at')->label('Started')->dateTime(),
                                        TextEntry::make('finished_at')->label('Finished')->dateTime(),
                                        TextEntry::make('source_release_path')->label('Source release')->copyable(),
                                        TextEntry::make('snapshot_path')->label('Snapshot path')->copyable(),
                                        TextEntry::make('restored_release_path')->label('Restored release')->copyable(),
                                        TextEntry::make('size_bytes')->label('Size bytes'),
                                        TextEntry::make('checksum')->label('Checksum')->copyable(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('Output')
                            ->schema([
                                Section::make('Logs')
                                    ->schema([
                                        TextEntry::make('error_message')->label('Error')->columnSpanFull(),
                                        TextEntry::make('recovery_hint')->label('Recovery hint')->columnSpanFull(),
                                        TextEntry::make('output')->label('Output')->columnSpanFull(),
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
