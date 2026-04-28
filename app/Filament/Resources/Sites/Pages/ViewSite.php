<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\BootstrapDeployPath;
use App\Actions\DeployProject;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Deployment;
use App\Models\SiteBackup;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Backups\SiteBackupService;
use App\Services\Cpanel\CpanelDomainProvisioner;
use App\Services\Cpanel\CpanelInventoryRepairService;
use App\Services\Cpanel\CpanelInventorySyncService;
use App\Services\Cpanel\CpanelSslProvisioner;
use App\Services\Deployment\ReleaseManager;
use App\Services\Dns\CloudflareDnsProvisioner;
use App\Services\GitHub\WebhookProvisioner;
use App\Services\Processes\SiteProcessService;
use App\Services\Server\VpsVhostInventorySyncService;
use App\Services\Server\VpsVhostRepairPlanService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
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
            Action::make('showGuide')
                ->label('Dashboard guide')
                ->icon('heroicon-m-academic-cap')
                ->iconButton()
                ->color('gray')
                ->modalWidth('4xl')
                ->modalHeading('Site Management Guide')
                ->modalFooterActions([])
                ->modalContent(fn (): View => view('filament.sites.management-guide')),
            Action::make('openLiveSite')
                ->label('Open live site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->visible(fn (): bool => filled($this->record->primary_domain))
                ->url(fn (): string => sprintf(
                    '%s://%s',
                    $this->record->force_https || in_array((string) $this->record->ssl_state, ['valid', 'issued', 'active', 'installed'], true) ? 'https' : 'http',
                    $this->record->primary_domain,
                ))
                ->openUrlInNewTab(),
            ActionGroup::make([
                Action::make('deploy')
                    ->label('Deploy now')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Queue a deployment?')
                    ->modalDescription('This creates a new deployment record and hands it to the queue worker.')
                    ->modalSubmitActionLabel('Queue deployment')
                    ->visible(fn (): bool => $this->record->active)
                    ->action(fn () => $this->deploySite()),
                Action::make('openTerminal')
                    ->label('Open terminal')
                    ->icon('heroicon-o-command-line')
                    ->color('gray')
                    ->outlined()
                    ->url(fn (): string => static::getResource()::getUrl('view', [
                        'record' => $this->record,
                        'tab' => 'terminal',
                    ]).'#site-terminal'),
            ])
                ->label('Ship')
                ->icon('heroicon-m-paper-airplane')
                ->color('primary')
                ->button(),

            ActionGroup::make([
                Action::make('bootstrapDeployPath')
                    ->label('Bootstrap path')
                    ->icon('heroicon-o-cube-transparent')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->deploy_path) && $this->record->server?->connection_type !== 'cpanel')
                    ->modalHeading('Bootstrap the deployment path?')
                    ->modalDescription('This checks the server, then creates the releases and shared directories needed.')
                    ->modalSubmitActionLabel('Bootstrap path')
                    ->action(fn () => $this->bootstrapDeployPath()),
                Action::make('provisionDomain')
                    ->label('Provision domain')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->primary_domain) && (bool) $this->record->server?->can_manage_domains)
                    ->modalWidth('7xl')
                    ->modalHeading('Provision the site domain?')
                    ->modalDescription('This creates the addon domain in cPanel.')
                    ->modalContent(fn (): View => view('filament.sites.cpanel-domain-provision-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Provision domain')
                    ->action(fn () => $this->provisionDomain()),
                Action::make('provisionDns')
                    ->label('Provision DNS')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->visible(fn (): bool => (bool) ($this->record->server?->can_manage_dns) && ($this->record->server?->dns_provider === 'cloudflare') && filled($this->record->primary_domain))
                    ->modalWidth('7xl')
                    ->modalHeading('Provision Cloudflare DNS?')
                    ->modalDescription('This creates or updates the DNS records.')
                    ->modalContent(fn (): View => view('filament.sites.dns-provision-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Provision DNS')
                    ->action(fn () => $this->provisionDns()),
                Action::make('provisionSsl')
                    ->label('Provision SSL')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->primary_domain) && (bool) $this->record->server?->can_manage_ssl)
                    ->modalWidth('7xl')
                    ->modalHeading('Provision SSL for this site?')
                    ->modalDescription('This generates a cPanel SSL certificate.')
                    ->modalContent(fn (): View => view('filament.sites.ssl-provision-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Provision SSL')
                    ->action(fn () => $this->provisionSsl()),
                Action::make('refreshSslStatus')
                    ->label('Refresh SSL status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->primary_domain) && (bool) $this->record->server?->can_manage_ssl)
                    ->requiresConfirmation()
                    ->modalHeading('Refresh SSL status?')
                    ->modalDescription('This starts a new AutoSSL check for the primary domain and updates the local SSL sync timestamp.')
                    ->modalSubmitActionLabel('Refresh SSL')
                    ->action(fn () => $this->refreshSslStatus()),
                Action::make('syncHttpsRedirect')
                    ->label('Sync HTTPS redirect')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->primary_domain) && (bool) $this->record->server?->can_manage_ssl)
                    ->requiresConfirmation()
                    ->modalHeading('Sync HTTPS redirect?')
                    ->modalDescription('This applies the current Force HTTPS setting from the site record to cPanel.')
                    ->modalSubmitActionLabel('Sync redirect')
                    ->action(fn () => $this->syncHttpsRedirect()),
            ])
                ->label('Provisioning')
                ->icon('heroicon-m-bolt')
                ->color('warning')
                ->button()
                ->outlined(),

            ActionGroup::make([
                Action::make('syncInventory')
                    ->label('Sync inventory')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->server?->cpanel_api_token))
                    ->modalWidth('7xl')
                    ->modalHeading('Sync the live cPanel inventory?')
                    ->modalContent(fn (): View => view('filament.sites.cpanel-inventory-sync-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Sync inventory')
                    ->action(fn () => $this->syncInventory()),
                Action::make('repairInventory')
                    ->label('Repair drift')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel' && filled($this->record->server?->cpanel_api_token))
                    ->modalWidth('7xl')
                    ->modalHeading('Repair the live cPanel inventory?')
                    ->modalContent(fn (): View => view('filament.sites.cpanel-inventory-repair-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Repair drift')
                    ->requiresConfirmation()
                    ->action(fn () => $this->repairInventory()),
                Action::make('syncVhostInventory')
                    ->label('Sync vhost inventory')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(fn (): bool => $this->record->server?->connection_type !== 'cpanel' && (bool) $this->record->server?->can_manage_vhosts)
                    ->modalWidth('7xl')
                    ->modalHeading('Sync the live vhost inventory?')
                    ->modalContent(fn (): View => view('filament.sites.vhost-inventory-sync-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Sync vhost inventory')
                    ->action(fn () => $this->syncVhostInventory()),
                Action::make('repairVhostPlan')
                    ->label('Repair vhost plan')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->server?->connection_type !== 'cpanel' && (bool) $this->record->server?->can_manage_vhosts)
                    ->modalWidth('7xl')
                    ->modalHeading('Review the VPS repair plan?')
                    ->modalContent(fn (): View => view('filament.sites.vhost-repair-plan-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Close')
                    ->action(fn () => $this->repairVhostPlan()),
                Action::make('applyVhostConfig')
                    ->label('Apply vhost config')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->server?->connection_type !== 'cpanel' && (bool) $this->record->server?->can_manage_vhosts)
                    ->modalWidth('7xl')
                    ->modalHeading('Apply the VPS vhost config?')
                    ->modalContent(fn (): View => view('filament.sites.vhost-repair-plan-modal', [
                        'record' => $this->record->fresh(['server']),
                    ]))
                    ->modalSubmitActionLabel('Apply config')
                    ->requiresConfirmation()
                    ->action(fn () => $this->applyVhostConfig()),
            ])
                ->label('Diagnostics')
                ->icon('heroicon-m-beaker')
                ->color('info')
                ->button()
                ->outlined(),
            ActionGroup::make([
                Action::make('provisionCpanelSite')
                    ->label('cPanel site wizard')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('primary')
                    ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel')
                    ->steps([
                        Step::make('Workspace')
                            ->key('cpanel-provision-workspace-step')
                            ->description('Review the cPanel account and deployment target.')
                            ->schema([
                                SchemaView::make('filament.sites.cpanel-provision-wizard'),
                            ]),
                        Step::make('Confirm')
                            ->key('cpanel-provision-confirm-step')
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
                Action::make('restartQueueWorkers')
                    ->label('Restart queue workers')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->deploy_path) && filled($this->record->server))
                    ->modalHeading('Restart queue workers?')
                    ->modalDescription('This will run php artisan queue:restart inside the current release directory.')
                    ->modalSubmitActionLabel('Restart workers')
                    ->action(fn () => $this->restartQueueWorkers()),
                Action::make('terminateHorizon')
                    ->label('Terminate Horizon')
                    ->icon('heroicon-o-stop-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->deploy_path) && filled($this->record->server))
                    ->modalHeading('Terminate Horizon?')
                    ->modalDescription('This will stop the Horizon daemon so the supervisor or process manager can restart it cleanly.')
                    ->modalSubmitActionLabel('Terminate Horizon')
                    ->action(fn () => $this->terminateHorizon()),
                Action::make('restartSupervisor')
                    ->label('Restart supervisor')
                    ->icon('heroicon-o-server-stack')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->deploy_path) && filled($this->record->server))
                    ->modalHeading('Restart supervisor?')
                    ->modalDescription('This restarts all supervised processes for the site environment.')
                    ->modalSubmitActionLabel('Restart supervisor')
                    ->action(fn () => $this->restartSupervisor()),
                Action::make('checkDaemonStatus')
                    ->label('Check daemon status')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => filled($this->record->deploy_path) && filled($this->record->server))
                    ->modalHeading('Check daemon status?')
                    ->modalDescription('This checks supervisor, Horizon, and queue workers so you can see which background processes are alive.')
                    ->modalSubmitActionLabel('Check status')
                    ->action(fn () => $this->checkDaemonStatus()),
            ])
                ->label('Processes')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('success')
                ->outlined()
                ->button()
                ->size('sm'),
            ActionGroup::make([
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
            ])
                ->label('Backups')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->outlined()
                ->button()
                ->size('sm'),
            ActionGroup::make([
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

    protected function provisionDomain(): void
    {
        try {
            $summary = app(CpanelDomainProvisioner::class)->provision($this->record->fresh(['server']));

            Notification::make()
                ->title('Domain provisioning finished')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to provision domain')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function provisionDns(): void
    {
        try {
            $summary = app(CloudflareDnsProvisioner::class)->provision($this->record->fresh(['server']));

            Notification::make()
                ->title('DNS provisioning finished')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to provision DNS')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function provisionSsl(): void
    {
        try {
            $site = $this->record->fresh(['server']);
            $summary = app(CpanelSslProvisioner::class)->provision($site);

            app(OperationalAlertService::class)->siteSslRefreshed(
                $site,
                implode(' ', $summary),
            );

            Notification::make()
                ->title('SSL provisioning finished')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            app(OperationalAlertService::class)->siteSslActionFailed(
                $this->record->fresh(['server']),
                'SSL provisioning',
                $throwable->getMessage(),
            );

            Notification::make()
                ->title('Unable to provision SSL')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function refreshSslStatus(): void
    {
        try {
            $site = $this->record->fresh(['server']);
            $summary = app(CpanelSslProvisioner::class)->refreshStatus($site);

            app(OperationalAlertService::class)->siteSslRefreshed(
                $site,
                implode(' ', $summary),
            );

            Notification::make()
                ->title('SSL status refreshed')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            app(OperationalAlertService::class)->siteSslActionFailed(
                $this->record->fresh(['server']),
                'SSL refresh',
                $throwable->getMessage(),
            );

            Notification::make()
                ->title('Unable to refresh SSL status')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function syncHttpsRedirect(): void
    {
        try {
            $site = $this->record->fresh(['server']);
            $summary = app(CpanelSslProvisioner::class)->syncHttpsRedirect($site);

            app(OperationalAlertService::class)->siteHttpsRedirectSynced(
                $site,
                implode(' ', $summary),
            );

            Notification::make()
                ->title('HTTPS redirect synced')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            app(OperationalAlertService::class)->siteSslActionFailed(
                $this->record->fresh(['server']),
                'HTTPS redirect sync',
                $throwable->getMessage(),
            );

            Notification::make()
                ->title('Unable to sync HTTPS redirect')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function syncInventory(): void
    {
        try {
            $summary = app(CpanelInventorySyncService::class)->sync($this->record->fresh(['server']));

            Notification::make()
                ->title('Live inventory synced')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to sync inventory')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function syncVhostInventory(): void
    {
        try {
            $summary = app(VpsVhostInventorySyncService::class)->sync($this->record->fresh(['server']));

            Notification::make()
                ->title('Vhost inventory synced')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to sync vhost inventory')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function repairVhostPlan(): void
    {
        try {
            $preview = app(VpsVhostRepairPlanService::class)->preview($this->record->fresh(['server']));

            Notification::make()
                ->title('VPS repair plan ready')
                ->body(sprintf('%s %s', $preview['engine_label'] ?? 'VPS', $preview['message'] ?? 'Repair plan generated.'))
                ->info()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to build repair plan')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function repairInventory(): void
    {
        try {
            $summary = app(CpanelInventoryRepairService::class)->repair($this->record->fresh(['server']));

            Notification::make()
                ->title('Inventory repair finished')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to repair inventory')
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

    protected function restartQueueWorkers(): void
    {
        $this->runProcessAction('queue_restart', 'Queue workers restarted', 'Unable to restart queue workers.');
    }

    protected function terminateHorizon(): void
    {
        $this->runProcessAction('horizon_terminate', 'Horizon terminated', 'Unable to terminate Horizon.');
    }

    protected function restartSupervisor(): void
    {
        $this->runProcessAction('supervisor_restart', 'Supervisor restarted', 'Unable to restart supervisor.');
    }

    protected function checkDaemonStatus(): void
    {
        $this->runProcessAction('daemon_status', 'Daemon status checked', 'Unable to check daemon status.');
    }

    protected function runProcessAction(string $action, string $successTitle, string $failureTitle): void
    {
        try {
            $run = app(SiteProcessService::class)->run($this->record->fresh(['server']), $action, auth()->user());

            Notification::make()
                ->title($successTitle)
                ->body(trim((string) ($run->output ?? '')) !== '' ? (string) $run->output : 'The process command finished successfully.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title($failureTitle)
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
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

    protected function applyVhostConfig(): void
    {
        try {
            $result = app(VpsVhostRepairPlanService::class)->apply($this->record->fresh(['server']));

            Notification::make()
                ->title('VPS vhost config applied')
                ->body($result['summary'] ?? 'The live vhost config was applied successfully.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to apply vhost config')
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
