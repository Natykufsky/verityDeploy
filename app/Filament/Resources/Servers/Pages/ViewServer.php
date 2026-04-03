<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Jobs\CheckServerHealth;
use App\Models\CredentialProfile;
use App\Models\ServerConnectionTest;
use App\Services\Security\SshKeyService;
use App\Services\Server\Connections\SshPasswordStrategy;
use App\Services\Server\ServerConnector;
use App\Services\Server\ServerKeyGenerator;
use App\Services\Server\ServerProvisioner;
use App\Services\Server\ServerPuTTYKeyExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;
use Throwable;

class ViewServer extends ViewRecord
{
    protected static string $resource = ServerResource::class;

    protected ?string $pollingInterval = '10s';

    public string $timelineFilter = 'all';

    public ?string $generatedSshKeyPublicKey = null;

    public ?string $generatedPuTTYKey = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCpanelConnectionWizard')
                ->label('Run all cPanel steps')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (): bool => $this->record->connection_type === 'cpanel')
                ->url(fn (): string => static::getResource()::getUrl('cpanel-wizard', [
                    'record' => $this->record,
                ])),
            Action::make('testConnection')
                ->label('Test Connection')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalHeading('Server connection details')
                ->modalDescription('Run a live whoami check through the configured connection strategy, save the result, and review the latest connection attempts.')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Run connection test')
                ->modalContent(fn (): View => view('filament.servers.connection-details-modal', [
                    'record' => $this->record->load([
                        'connectionTests' => fn ($query) => $query->latest('tested_at')->latest()->limit(5),
                    ]),
                ]))
                ->action(fn () => $this->testConnection()),
            Action::make('generateSshKey')
                ->label('Generate SSH Key')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->modalHeading('Generate a new Ed25519 key pair?')
                ->modalDescription('This replaces the saved encrypted SSH private key, switches the server to SSH key authentication, and reveals the public key you should paste into the remote server.')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalContent(fn (): View => view('filament.servers.generate-ssh-key-modal', [
                    'record' => $this->record,
                    'generatedPublicKey' => $this->generatedSshKeyPublicKey,
                ]))
                ->action(function (): void {}),
            Action::make('exportPuTTYKey')
                ->label('Export PuTTY Key')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->ssh_key))
                ->modalHeading('Export a PuTTY-compatible private key?')
                ->modalDescription('This converts the saved SSH key into a .ppk file for PuTTY and other Windows SSH tooling without changing the stored private key.')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalContent(fn (): View => view('filament.servers.export-putty-key-modal', [
                    'record' => $this->record,
                    'generatedPuTTYKey' => $this->generatedPuTTYKey,
                ]))
                ->action(function (): void {}),
            Action::make('provisionServer')
                ->label('Provision server')
                ->icon('heroicon-o-server-stack')
                ->color('primary')
                ->requiresConfirmation()
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalHeading('Run server provisioning checks?')
                ->modalDescription('Checks disk space, PHP, and Git before the server is used for bootstrap. Composer is treated as optional here, and the result is recorded for later review.')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Run checks')
                ->modalContent(fn (): View => view('filament.servers.connection-details-modal', [
                    'record' => $this->record->load([
                        'connectionTests' => fn ($query) => $query->latest('tested_at')->latest()->limit(5),
                    ]),
                ]))
                ->action(fn () => $this->provisionServer()),
            Action::make('checkHealth')
                ->label('Check health')
                ->icon('heroicon-o-heart')
                ->color('success')
                ->action(fn () => $this->checkHealth()),
            Action::make('authorizeSshKey')
                ->label('Authorize SSH Key')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->modalHeading('Authorize a Credential Profile?')
                ->modalDescription('Select an SSH profile to push its public key to this server. If you don\'t have access yet, you can provide a bootstrap password.')
                ->form([
                    Select::make('credential_profile_id')
                        ->label('SSH Profile')
                        ->options(fn () => CredentialProfile::ofType('ssh')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    TextInput::make('bootstrap_password')
                        ->label('One-time password')
                        ->password()
                        ->revealable()
                        ->helperText('Optional. If the current server key/password doesn\'t work, this will be used for the authorization step only.'),
                ])
                ->action(fn (array $data) => $this->authorizeSshKey($data)),
        ];
    }

    protected function testConnection(): void
    {
        $test = ServerConnectionTest::query()->create([
            'server_id' => $this->record->id,
            'status' => 'running',
            'command' => 'whoami',
            'tested_at' => now(),
        ]);

        try {
            $output = app(ServerConnector::class)->strategy($this->record, 10)->run('whoami');

            $this->record->update([
                'status' => 'online',
                'last_connected_at' => now(),
            ]);

            $test->update([
                'status' => 'successful',
                'output' => $output,
                'exit_code' => 0,
                'error_message' => null,
            ]);

            Notification::make()
                ->title('Connection succeeded')
                ->body(trim($output) !== '' ? "{$this->record->name} responded successfully as {$output}." : "{$this->record->name} responded successfully.")
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            $this->record->update([
                'status' => 'error',
            ]);

            $test->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->title('Connection failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function generateSshKey(): void
    {
        try {
            $result = app(ServerKeyGenerator::class)->generate($this->record);
            $this->generatedSshKeyPublicKey = $result['public_key'];

            Notification::make()
                ->title('SSH key generated')
                ->body('A new OpenSSH Ed25519 private key was saved to the server record. The public key is ready to copy in the modal.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to generate SSH key')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportPuTTYKey(): void
    {
        try {
            $result = app(ServerPuTTYKeyExporter::class)->export($this->record);
            $this->generatedPuTTYKey = $result['putty_private_key'];

            Notification::make()
                ->title('PuTTY key exported')
                ->body('The .ppk export is ready to copy from the modal.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to export PuTTY key')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function provisionServer(): void
    {
        try {
            app(ServerProvisioner::class)->preflight($this->record);

            Notification::make()
                ->title('Server provisioned')
                ->body('Disk, PHP, and Git checks passed successfully. Composer was treated as optional for bootstrap.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to provision server')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function checkHealth(): void
    {
        try {
            CheckServerHealth::dispatch($this->record->id);

            Notification::make()
                ->title('Health check queued')
                ->body('The server health job is running in the background.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to queue health check')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function authorizeSshKey(array $data): void
    {
        $profile = CredentialProfile::findOrFail($data['credential_profile_id']);
        $publicKey = data_get($profile->settings, 'public_key');

        if (blank($publicKey)) {
            $publicKey = app(SshKeyService::class)->derivePublicKey(data_get($profile->settings, 'private_key'), (string) data_get($profile->settings, 'passphrase'));
        }

        if (blank($publicKey)) {
            Notification::make()
                ->title('Invalid key')
                ->body('Could not find or derive a public key for this profile.')
                ->danger()
                ->send();

            return;
        }

        try {
            $server = $this->record;

            if (filled($data['bootstrap_password'])) {
                // One-time bootstrap password
                $tempServer = $server->replicate();
                $tempServer->id = $server->id; // Keep ID for strategy logs if needed

                $strategy = new SshPasswordStrategy(
                    server: $tempServer,
                    timeout: 30
                );

                // We must trick the effectiveSudoPassword by making it think it's part of a profile
                // or just manually setting it if SshPasswordStrategy allowed it.
                // Since SshPasswordStrategy uses $this->server->sudo_password, and that's a magic attribute:
                // We'll use a dynamic property on the model for this run.
                $tempServer->forceFill(['sudo_password' => $data['bootstrap_password']]);
            } else {
                $strategy = app(ServerConnector::class)->strategy($this->record);
            }

            $command = sprintf(
                'mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo %s >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys',
                escapeshellarg(trim((string) $publicKey))
            );

            $strategy->run($command);

            Notification::make()
                ->title('SSH key authorized')
                ->body("Public key for \"{$profile->name}\" has been added to authorized_keys on {$this->record->name}.")
                ->success()
                ->send();

        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Authorization failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }
}
