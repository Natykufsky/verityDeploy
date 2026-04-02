<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Models\Team;
use App\Services\Cpanel\CpanelApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Throwable;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        $isCreatePage = $schema->getOperation() === 'create';

        $foundationFields = [
            Section::make('Foundation')
                ->description('Platform identity and basic connectivity.')
                ->icon('heroicon-o-building-office-2')
                ->schema([
                    Select::make('connection_type')
                        ->label('Connection System')
                        ->options([
                            'ssh_key' => 'SSH Key Auth',
                            'password' => 'Direct Password',
                            'cpanel' => 'cPanel / WHM API',
                        ])
                        ->default('ssh_key')
                        ->required()
                        ->live()
                        ->disabled(! $isCreatePage)
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label('Server Name')
                        ->placeholder('e.g. Production Node 1')
                        ->required(),
                    TextInput::make('ip_address')
                        ->label('Public IP Address')
                        ->placeholder('123.123.123.123')
                        ->required(),
                    Select::make('team_id')
                        ->label('Owner Team')
                        ->options(Team::query()->accessibleTo()->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('No specific team'),
                ])->columns(['md' => 2]),
        ];

        $authFields = [
            Section::make('Terminal Access')
                ->description('Credentials used to establish the initial connection.')
                ->icon('heroicon-o-command-line')
                ->schema([
                    TextInput::make('ssh_port')
                        ->label('SSH Port')
                        ->numeric()
                        ->default(22)
                        ->required()
                        ->helperText('The network port for terminal access. Use the search icon to auto-discover.')
                        ->suffixAction(
                            Action::make('discoverPort')
                                ->icon('heroicon-o-magnifying-glass')
                                ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel')
                                ->action(function (Get $get, Set $set) {
                                    $profileId = $get('cpanel_credential_profile_id');
                                    $ip = $get('ip_address');

                                    if (! $profileId || ! $ip) {
                                        Notification::make()
                                            ->title('IP and cPanel Profile required')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    try {
                                        // Build a transient server to use the discovery service
                                        $tempServer = new Server([
                                            'ip_address' => $ip,
                                            'cpanel_credential_profile_id' => $profileId,
                                            'connection_type' => 'cpanel',
                                        ]);

                                        $discoveredPort = app(CpanelApiClient::class)->discoverSshPort($tempServer);

                                        $set('ssh_port', $discoveredPort);

                                        Notification::make()
                                            ->title('SSH Port discovered: '.$discoveredPort)
                                            ->success()
                                            ->send();
                                    } catch (Throwable $e) {
                                        Notification::make()
                                            ->title('Auto-discovery failed')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                })
                        ),
                    TextInput::make('ssh_user')
                        ->label('SSH User')
                        ->default('root')
                        ->required()
                        ->helperText('Remote account name (e.g. root, forge).'),
                    TextInput::make('password')
                        ->password()
                        ->label('SSH Password')
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'password')
                        ->required(fn (Get $get): bool => $get('connection_type') === 'password'),
                    Select::make('cpanel_credential_profile_id')
                        ->label('cPanel Profile')
                        ->options(CredentialProfile::query()->ofType('cpanel')->where('is_active', true)->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'cpanel')
                        ->columnSpanFull(),
                ])->columns(['md' => 2]),
        ];

        $securityFields = [
            Section::make('Security Hub')
                ->description('Authorize the dashboard to manage this server.')
                ->schema([
                    View::make('filament.servers.ssh-key-display')
                        ->columnSpanFull(),
                    TextInput::make('manual_status')
                        ->label('Manual Status')
                        ->default('Dashboard Public Key is shown above. Copy it into your authorized_keys file.')
                        ->readOnly()
                        ->columnSpanFull(),
                    Actions::make([
                        Action::make('generateNewKey')
                            ->label('Regenerate Dashboard Key')
                            ->icon('heroicon-o-key')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Regenerate Master SSH Key?')
                            ->modalDescription('Existing authorized connections to other servers might break. Continue?')
                            ->action(function () {
                                try {
                                    $sshDir = base_path('.ssh');
                                    if (! file_exists($sshDir)) {
                                        mkdir($sshDir, 0700, true);
                                    }
                                    shell_exec('ssh-keygen -t rsa -b 4096 -f '.escapeshellarg($sshDir.'/id_rsa')." -N '' -q");
                                    Notification::make()->title('Master SSH Key Regenerated')->success()->send();
                                } catch (Throwable $e) {
                                    Notification::make()->title('Key Generation Failed')->body($e->getMessage())->danger()->send();
                                }
                            }),

                        Action::make('importKey')
                            ->label('Import Key Pair')
                            ->icon('heroicon-o-document-arrow-down')
                            ->color('warning')
                            ->form([
                                Textarea::make('private_key')->label('Private Key')->required()->rows(10),
                                Textarea::make('public_key')->label('Public Key')->required()->rows(4),
                            ])
                            ->action(function (array $data) {
                                try {
                                    $sshDir = base_path('.ssh');
                                    if (! file_exists($sshDir)) {
                                        mkdir($sshDir, 0700, true);
                                    }
                                    file_put_contents($sshDir.'/id_rsa', $data['private_key']);
                                    file_put_contents($sshDir.'/id_rsa.pub', $data['public_key']);
                                    chmod($sshDir.'/id_rsa', 0600);
                                    Notification::make()->title('SSH Key Pair Imported')->success()->send();
                                } catch (Throwable $e) {
                                    Notification::make()->title('Import Failed')->body($e->getMessage())->danger()->send();
                                }
                            }),

                        Action::make('authorizeAutomatically')
                            ->label('Authorize Automatically')
                            ->icon('heroicon-o-cloud-arrow-up')
                            ->color('success')
                            ->visible(fn (Get $get) => $get('connection_type') === 'cpanel')
                            ->requiresConfirmation()
                            ->modalHeading('Authorize SSH Key?')
                            ->action(function (Server $record) {
                                try {
                                    $publicKey = @file_get_contents(base_path('.ssh/id_rsa.pub'));
                                    if (! $publicKey) {
                                        throw new \Exception('Dashboard key not found.');
                                    }
                                    app(CpanelApiClient::class)->authorizeSshKey($record, $publicKey);
                                    Notification::make()->title('Key authorized on cPanel.')->success()->send();
                                } catch (Throwable $e) {
                                    Notification::make()->title('Auto-Authorization Failed')->body($e->getMessage())->danger()->send();
                                }
                            }),
                    ])->columnSpanFull(),
                ]),
        ];

        return $schema->components([
            Hidden::make('user_id')->default(fn (): ?int => auth()->id()),
            Wizard::make([
                Step::make('Foundation')->description('Platform & IP')->schema($foundationFields),
                Step::make('Access')->description('Credentials')->schema($authFields),
                Step::make('Security')->description('Handshake')->schema($securityFields),
                Step::make('Ready')
                    ->description($isCreatePage ? 'Finalize' : 'Update')
                    ->schema([
                        Section::make('Audit')
                            ->schema([
                                View::make('filament.servers.creation-summary')->columnSpanFull(),
                                DateTimePicker::make('last_connected_at')
                                    ->label('Last Heartbeat')
                                    ->visible(! $isCreatePage)
                                    ->disabled(),
                                TextInput::make('status')
                                    ->label('Current Status')
                                    ->visible(! $isCreatePage)
                                    ->disabled(),
                            ])->columns(['md' => 2]),
                    ]),
            ])->columnSpanFull()
                ->persistStepInQueryString('step'),
        ]);
    }
}
