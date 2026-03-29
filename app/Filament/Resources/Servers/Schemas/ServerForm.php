<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Models\Server;
use App\Services\AppSettings;
use App\Services\Cpanel\CpanelApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Throwable;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn (): ?int => auth()->id()),
                Tabs::make('Server Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('General Info')
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('ip_address')
                                    ->label('IP address')
                                    ->required(),
                                TextInput::make('ssh_port')
                                    ->label('SSH port')
                                    ->required()
                                    ->numeric()
                                    ->default(fn (): int => app(AppSettings::class)->defaultSshPort())
                                    ->suffixAction(
                                        Action::make('discoverCpanelSshPort')
                                            ->label('Discover')
                                            ->icon('heroicon-o-magnifying-glass')
                                            ->visible(fn (?Server $record, Get $get): bool => $get('connection_type') === 'cpanel' && filled($record))
                                            ->action(function (?Server $record, Set $set): void {
                                                if (! $record) {
                                                    Notification::make()
                                                        ->title('Save the server first')
                                                        ->body('Create or update the cPanel server before discovering its SSH port.')
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                try {
                                                    $port = app(CpanelApiClient::class)->discoverSshPort($record->fresh());

                                                    $set('ssh_port', $port);
                                                    $record->update([
                                                        'ssh_port' => $port,
                                                    ]);

                                                    Notification::make()
                                                        ->title('SSH port discovered')
                                                        ->body("The cPanel account SSH port was set to {$port}.")
                                                        ->success()
                                                        ->send();
                                                } catch (Throwable $throwable) {
                                                    Notification::make()
                                                        ->title('Unable to discover SSH port')
                                                        ->body($throwable->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    )
                                    ->helperText('Use Discover after saving a cPanel server to fetch the account SSH port from the API.'),
                                TextInput::make('ssh_user')
                                    ->label('SSH user')
                                    ->required(),
                                Select::make('connection_type')
                                    ->options([
                                        'ssh_key' => 'SSH key',
                                        'password' => 'Password',
                                        'local' => 'Local',
                                        'cpanel' => 'cPanel',
                                    ])
                                    ->default('ssh_key')
                                    ->required(),
                                TextInput::make('status')
                                    ->required()
                                    ->default('offline'),
                                DateTimePicker::make('last_connected_at'),
                                View::make('filament.servers.connection-mode-help')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('SSH Security')
                            ->schema([
                                Textarea::make('ssh_key')
                                    ->label('SSH private key')
                                    ->rows(12)
                                    ->columnSpanFull()
                                    ->helperText('Stored encrypted in the database. Use the generate key action to create a new Ed25519 pair.'),
                            ])
                            ->columns(1),
                        Tab::make('cPanel API')
                            ->schema([
                                TextInput::make('cpanel_api_token')
                                    ->label('cPanel API token')
                                    ->password()
                                    ->revealable()
                                    ->columnSpanFull()
                                    ->suffixAction(
                                        Action::make('testCpanelApi')
                                            ->label('Test API')
                                            ->icon('heroicon-o-bolt')
                                            ->visible(fn (?Server $record, Get $get): bool => $get('connection_type') === 'cpanel' && filled($record))
                                            ->action(function (?Server $record): void {
                                                if (! $record) {
                                                    Notification::make()
                                                        ->title('Save the server first')
                                                        ->body('Create or update the cPanel server before testing the API token.')
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                try {
                                                    app(CpanelApiClient::class)->ping($record->fresh());

                                                    $record->update([
                                                        'status' => 'online',
                                                        'last_connected_at' => now(),
                                                    ]);

                                                    Notification::make()
                                                        ->title('cPanel API responded')
                                                        ->body('The cPanel token and API port are reachable.')
                                                        ->success()
                                                        ->send();
                                                } catch (Throwable $throwable) {
                                                    Notification::make()
                                                        ->title('Unable to reach the cPanel API')
                                                        ->body($throwable->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    )
                                    ->helperText('Stored encrypted. Use the cPanel account username in the SSH user field, and use Test API to verify the token and port.'),
                                TextInput::make('cpanel_api_port')
                                    ->label('cPanel API port')
                                    ->numeric()
                                    ->default(2083)
                                    ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel'),
                            ])
                            ->columns(2),
                        Tab::make('Sudo Settings')
                            ->schema([
                                TextInput::make('sudo_password')
                                    ->label('Sudo / SSH password')
                                    ->password()
                                    ->revealable()
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}
