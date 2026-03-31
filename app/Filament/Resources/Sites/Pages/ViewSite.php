<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\DeployProject;
use App\Actions\BootstrapDeployPath;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Deployment;
use App\Models\SiteBackup;
use App\Services\Backups\SiteBackupService;
use App\Services\Deployment\ReleaseManager;
use App\Services\GitHub\WebhookProvisioner;
use Filament\Actions\ActionGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Contracts\View\View;
use Throwable;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected ?string $pollingInterval = '5s';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deploy')
                ->label('Deploy')
                ->icon('heroicon-o-rocket-launch')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Queue a deployment?')
                ->modalDescription('This creates a new deployment record and hands it to the queue worker so the selected site can deploy in the background.')
                ->modalSubmitActionLabel('Queue deployment')
                ->action(fn () => $this->deploySite()),
            Action::make('openTerminal')
                ->label('Open terminal')
                ->icon('heroicon-o-command-line')
                ->color('gray')
                ->outlined()
                ->url(fn (): string => static::getResource()::getUrl('view', [
                    'record' => $this->record,
                    'tab' => 'terminal',
                ]) . '#site-terminal'),
            Action::make('bootstrapDeployPath')
                ->label('Bootstrap path')
                ->icon('heroicon-o-cube-transparent')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => filled($this->record->deploy_path) && $this->record->server?->connection_type !== 'cpanel')
                ->modalHeading('Bootstrap the deployment path?')
                ->modalDescription('This checks the server, then creates the releases and shared directories needed for the first git-based deploy so future deploys can switch releases safely.')
                ->modalSubmitActionLabel('Bootstrap path')
                ->action(fn () => $this->bootstrapDeployPath()),
            ActionGroup::make([
                Action::make('provisionCpanelSite')
                    ->label('cPanel site wizard')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('primary')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel')
                    ->modalWidth('7xl')
                    ->steps([
                        Step::make('Workspace')
                            ->description('Review the cPanel account and deployment target.')
                            ->schema([
                                SchemaView::make('filament.sites.cpanel-provision-wizard'),
                            ]),
                        Step::make('Confirm')
                            ->description('Confirm that the cPanel workspace should be created.')
                            ->schema([
                                Toggle::make('confirm_provisioning')
                                    ->label('I want to provision this cPanel site')
                                    ->accepted()
                                    ->helperText('This creates the workspace and shared runtime files on the cPanel server.'),
                            ]),
                    ])
                    ->modalHeading('cPanel site provisioning wizard')
                    ->modalDescription('Use this wizard before the first deploy to prepare the cPanel workspace, verify the connection details, and review the exact steps that will run.')
                    ->modalSubmitActionLabel('Provision site')
                    ->action(fn () => $this->bootstrapDeployPath()),
                Action::make('runCpanelBootstrapWizard')
                    ->label('Run cPanel bootstrap')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel')
                    ->url(fn (): string => static::getResource()::getUrl('cpanel-bootstrap-wizard', [
                        'record' => $this->record,
                    ])),
                Action::make('reRunCpanelBootstrap')
                    ->label('Re-run cPanel bootstrap')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->deploy_path))
                    ->requiresConfirmation()
                    ->modalHeading('Re-run cPanel bootstrap?')
                    ->modalDescription('This re-validates the cPanel API, ensures the workspace directories exist, re-syncs shared files, and refreshes the deployment path without changing releases.')
                    ->modalSubmitActionLabel('Re-run bootstrap')
                    ->action(fn () => $this->bootstrapDeployPath()),
                Action::make('cleanupReleases')
                    ->label('Clean releases')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->deploy_source === 'git' && filled($this->record->deploy_path))
                    ->requiresConfirmation()
                    ->modalHeading('Remove old release folders?')
                    ->modalDescription('This keeps the latest 5 releases and removes older release directories from the server to reduce disk usage.')
                    ->modalSubmitActionLabel('Clean releases')
                    ->action(fn () => $this->cleanupReleases()),
                Action::make('createBackup')
                    ->label('Create backup')
                    ->icon('heroicon-o-archive-box')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->current_release_path))
                    ->modalHeading('Create a release backup?')
                    ->modalDescription('This copies the current release into the backups directory so you can restore it later without rebuilding from Git or a local source archive.')
                    ->modalSubmitActionLabel('Create backup')
                    ->action(fn () => $this->createBackup()),
                Action::make('restoreBackup')
                    ->label('Restore backup')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (): bool => filled($this->record->backupOptions()))
                    ->modalWidth('4xl')
                    ->schema([
                        Select::make('backup_id')
                            ->label('Backup snapshot')
                            ->options(fn (): array => $this->record->fresh()->backupOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->helperText('Choose a successful backup snapshot to restore the current release from.'),
                        Placeholder::make('restore_preview')
                            ->label('Restore confirmation')
                            ->content(function (Get $get): string {
                                return $this->renderBackupPreview($get('backup_id'));
                            })
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('Restore a backup snapshot')
                    ->modalDescription('The selected backup will be copied into a fresh release directory and then activated as the current release.')
                    ->modalSubmitActionLabel('Restore backup')
                    ->requiresConfirmation()
                    ->action(fn (array $data) => $this->restoreBackup($data)),
                Action::make('restoreRelease')
                    ->label('Restore release')
                    ->icon('heroicon-o-backward')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->previousReleaseOptions()))
                    ->modalWidth('4xl')
                    ->schema([
                        Select::make('deployment_id')
                            ->label('Previous release')
                            ->options(fn (): array => $this->record->fresh()->previousReleaseOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->helperText('Choose a previous successful release from this site.'),
                        Placeholder::make('restore_preview')
                            ->label('Rollback confirmation')
                            ->content(function (Get $get): string {
                                return $this->renderRestorePreview($get('deployment_id'));
                            })
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('Restore a previous release')
                    ->modalDescription('Select a prior release to preview the exact release path that will be restored before you confirm the rollback.')
                    ->modalSubmitActionLabel('Restore release')
                    ->requiresConfirmation()
                    ->action(fn (array $data) => $this->restoreRelease($data)),
                Action::make('refreshWebhookStatus')
                    ->label('Refresh webhook status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn () => $this->refreshWebhookStatus()),
                Action::make('reProvisionWebhook')
                    ->label('Re-provision webhook')
                    ->icon('heroicon-o-signal')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->repository_url))
                    ->modalHeading('Re-provision GitHub webhook?')
                    ->modalDescription('This will create or update the webhook on GitHub so push deploys work again.')
                    ->action(fn () => $this->reProvisionWebhook()),
                Action::make('webhookSettings')
                    ->label('Webhook settings')
                    ->icon('heroicon-o-link')
                    ->url(fn (): string => static::getResource()::getUrl('webhooks', [
                        'record' => $this->record,
                    ])),
            ])
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->outlined()
                ->button()
                ->size('sm'),
        ];
    }

    protected function deploySite(): void
    {
        try {
            app(DeployProject::class)->dispatch($this->record, auth()->user());

            Notification::make()
                ->title('Deployment queued')
                ->body("{$this->record->name} has been queued for deployment.")
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to queue deployment')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function bootstrapDeployPath(): void
    {
        try {
            app(BootstrapDeployPath::class)->bootstrap($this->record->fresh(['server']));

            Notification::make()
                ->title('Deployment path bootstrapped')
                ->body('The remote releases and shared directories are ready for the first deploy.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to bootstrap deployment path')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function cleanupReleases(): void
    {
        try {
            app(ReleaseManager::class)->cleanupOldReleases($this->record->fresh(['server']));
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to clean releases')
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Release cleanup finished')
            ->body('Older release directories were rotated successfully.')
            ->success()
            ->send();
    }

    protected function createBackup(): void
    {
        try {
            app(SiteBackupService::class)->backup($this->record->fresh(['server']), auth()->user());

            Notification::make()
                ->title('Backup created')
                ->body('The current release was copied into the backups directory.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to create backup')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function refreshWebhookStatus(): void
    {
        try {
            $result = app(WebhookProvisioner::class)->refreshStatus($this->record->fresh());
            $status = data_get($result, 'status', $this->record->fresh()?->github_webhook_status);
            $message = match ($status) {
                'provisioned' => 'The remote webhook is present and healthy.',
                'needs-sync' => 'GitHub no longer has the expected webhook, so it needs to be re-provisioned.',
                'unprovisioned' => 'No remote webhook is currently configured.',
                default => 'Webhook status was refreshed successfully.',
            };

            Notification::make()
                ->title('Webhook status refreshed')
                ->body($message)
                ->status($status === 'needs-sync' ? 'warning' : ($status === 'unprovisioned' ? 'info' : 'success'))
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to refresh webhook status')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function reProvisionWebhook(): void
    {
        try {
            app(WebhookProvisioner::class)->provision($this->record->fresh());

        Notification::make()
            ->title('Webhook re-provisioned')
            ->body('The GitHub webhook was created or updated again.')
            ->success()
            ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to re-provision webhook')
            ->body($throwable->getMessage())
            ->danger()
            ->send();
        }
    }

    /**
     * @param  array{backup_id?: string|int|null}  $data
     */
    protected function restoreBackup(array $data): void
    {
        $backupId = $data['backup_id'] ?? null;

        if (blank($backupId)) {
            Notification::make()
                ->title('No backup selected')
                ->body('Choose a backup snapshot before restoring.')
                ->warning()
                ->send();

            return;
        }

        $backup = SiteBackup::query()
            ->whereKey($backupId)
            ->where('site_id', $this->record->id)
            ->where('operation', 'backup')
            ->where('status', 'successful')
            ->whereNotNull('snapshot_path')
            ->first();

        if (! $backup) {
            Notification::make()
                ->title('Backup not found')
                ->body('The selected backup snapshot is no longer available.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(SiteBackupService::class)->restore($backup, auth()->user());

            Notification::make()
                ->title('Backup restore queued')
                ->body(sprintf('The site will be restored from %s.', $backup->snapshot_path))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to restore backup')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array{deployment_id?: string|int|null}  $data
     */
    protected function restoreRelease(array $data): void
    {
        $deploymentId = $data['deployment_id'] ?? null;

        if (blank($deploymentId)) {
            Notification::make()
                ->title('No release selected')
                ->body('Choose a previous release before restoring.')
                ->warning()
                ->send();

            return;
        }

        $targetDeployment = Deployment::query()
            ->whereKey($deploymentId)
            ->where('site_id', $this->record->id)
            ->where('status', 'successful')
            ->whereNotNull('release_path')
            ->first();

        if (! $targetDeployment) {
            Notification::make()
                ->title('Release not found')
                ->body('The selected release is no longer available for restore.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(DeployProject::class)->rollback($targetDeployment, auth()->user());

            Notification::make()
                ->title('Rollback queued')
                ->body("{$this->record->name} is restoring {$targetDeployment->release_path}.")
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to queue rollback')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function renderBackupPreview(mixed $backupId): string
    {
        if (blank($backupId)) {
            return 'Select a previous backup to preview the exact snapshot that will be restored.';
        }

        $backup = SiteBackup::query()
            ->whereKey($backupId)
            ->where('site_id', $this->record->id)
            ->where('operation', 'backup')
            ->first();

        if (! $backup || blank($backup->snapshot_path)) {
            return 'The selected backup is unavailable. Choose a different backup to continue.';
        }

        $currentRelease = filled($this->record->current_release_path)
            ? $this->record->current_release_path
            : 'no current release is set';

        return sprintf(
            'This will restore exactly %s and then update the site current release from %s.',
            $backup->snapshot_path,
            $currentRelease,
        );
    }

    protected function renderRestorePreview(mixed $deploymentId): string
    {
        if (blank($deploymentId)) {
            return 'Select a previous release to preview the exact path that will be restored.';
        }

        $targetDeployment = Deployment::query()
            ->whereKey($deploymentId)
            ->where('site_id', $this->record->id)
            ->first();

        if (! $targetDeployment || blank($targetDeployment->release_path)) {
            return 'The selected release is unavailable. Choose a different release to continue.';
        }

        $currentRelease = filled($this->record->current_release_path)
            ? $this->record->current_release_path
            : 'no current release is set';

        return sprintf(
            'This will restore exactly %s and then update the site current release from %s.',
            $targetDeployment->release_path,
            $currentRelease,
        );
    }
}
