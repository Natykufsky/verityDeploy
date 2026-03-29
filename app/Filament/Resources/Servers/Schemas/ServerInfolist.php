<?php

namespace App\Filament\Resources\Servers\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ServerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server Details')
                    ->schema([
                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),
                        TextEntry::make('name'),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->copyable(),
                        TextEntry::make('ssh_port')
                            ->label('SSH port'),
                        TextEntry::make('ssh_user')
                            ->label('SSH user')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'online' => 'success',
                                'offline' => 'gray',
                                'error' => 'danger',
                                default => 'warning',
                            }),
                        TextEntry::make('connection_type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'ssh_key' => 'success',
                                'password' => 'warning',
                                'local' => 'gray',
                                'cpanel' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('last_connected_at')
                            ->dateTime(),
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Health Metrics')
                    ->schema([
                        KeyValueEntry::make('metrics')
                            ->label('Metrics')
                            ->keyLabel('Metric')
                            ->valueLabel('Value'),
                    ]),
                Section::make('Security')
                    ->schema([
                        TextEntry::make('ssh_key_state')
                            ->label('SSH key')
                            ->state(fn ($record): string => filled($record->ssh_key) ? 'configured' : 'missing')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'configured' ? 'success' : 'danger'),
                        TextEntry::make('sudo_password_state')
                            ->label('Sudo password')
                            ->state(fn ($record): string => filled($record->sudo_password) ? 'configured' : 'missing')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'configured' ? 'warning' : 'gray'),
                        TextEntry::make('cpanel_api_token_state')
                            ->label('cPanel API token')
                            ->state(fn ($record): string => filled($record->cpanel_api_token) ? 'configured' : 'missing')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'configured' ? 'info' : 'danger'),
                        TextEntry::make('cpanel_api_port')
                            ->label('cPanel API port'),
                    ])
                    ->columns(2),
                Section::make('Operational Timeline')
                    ->schema([
                        View::make('filament.schemas.components.operational-timeline'),
                    ]),
            ]);
    }

    protected static function renderTerminalBlock(mixed $state): HtmlString
    {
        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">%s</pre>',
            e((string) $state),
        ));
    }
}
