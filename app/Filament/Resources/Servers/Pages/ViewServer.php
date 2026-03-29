<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Widgets\ServerMetricsStats;
use App\Jobs\CheckServerHealth;
use App\Models\ServerConnectionTest;
use App\Services\Server\ServerPuTTYKeyExporter;
use App\Services\Server\ServerConnector;
use App\Services\Server\ServerKeyGenerator;
use App\Services\Server\ServerProvisioner;
use Filament\Actions\Action;
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
                ->action(function (): void {
                }),
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
                ->action(function (): void {
                }),
            Action::make('provisionServer')
                ->label('Provision server')
                ->icon('heroicon-o-server-stack')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run server provisioning checks?')
                ->modalDescription('Checks disk space, PHP, Composer, and Git before the server is used for bootstrap, and records the result for later review.')
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
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServerMetricsStats::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'recordId' => $this->record->id,
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
                ->body('A new encrypted Ed25519 private key was saved to the server record. The public key is ready to copy in the modal.')
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
                ->body('Disk, PHP, Composer, and Git checks passed successfully.')
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
}
