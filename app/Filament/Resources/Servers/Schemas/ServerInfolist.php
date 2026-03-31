<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Livewire\ServerTerminalConsole;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                Section::make('Server details')
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
                                        TextEntry::make('cpanel_username')
                                            ->label('cPanel username')
                                            ->placeholder('Same as SSH user if not set')
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
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                                Section::make('Provider')
                                    ->schema([
                                        TextEntry::make('provider_type')
                                            ->label('Provider')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'digitalocean' => 'info',
                                                'aws' => 'warning',
                                                'hetzner' => 'danger',
                                                'vultr', 'linode' => 'primary',
                                                'cpanel' => 'success',
                                                'local' => 'gray',
                                                default => 'slate',
                                            }),
                                        TextEntry::make('provider_reference')
                                            ->label('Provider reference')
                                            ->copyable(),
                                        TextEntry::make('provider_region')
                                            ->label('Provider region'),
                                        TextEntry::make('provider_summary')
                                            ->label('Summary')
                                            ->columnSpanFull(),
                                        KeyValueEntry::make('provider_metadata')
                                            ->label('Metadata')
                                            ->keyLabel('Field')
                                            ->valueLabel('Value')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
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
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('Metrics')
                            ->badge(fn ($record): string => 'Live')
                            ->badgeColor('success')
                            ->schema([
                                Section::make('Health metrics')
                                    ->schema([
                                        KeyValueEntry::make('metrics')
                                            ->label('Metrics')
                                            ->keyLabel('Metric')
                                            ->valueLabel('Value'),
                                    ]),
                            ]),
                        Tab::make('History')
                            ->badge(fn ($record): string => (string) count($record->connectionTests))
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Operational timeline')
                                    ->extraAttributes([
                                        'class' => 'operational-timeline-section',
                                    ])
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
                        Tab::make('Terminal')
                            ->badge('Live')
                            ->badgeColor('info')
                            ->schema([
                                Section::make('Server terminal')
                                    ->schema([
                                        Livewire::make(ServerTerminalConsole::class)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
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
