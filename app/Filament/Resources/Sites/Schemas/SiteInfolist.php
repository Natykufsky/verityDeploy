<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SiteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Site tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => $record->current_release_status === 'active' ? 'Live' : 'Setup')
                            ->badgeColor(fn ($record): string => $record->current_release_status === 'active' ? 'success' : 'warning')
                            ->schema([
                                Section::make('Site overview')
                                    ->schema([
                                        View::make('filament.sites.site-overview')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Site details')
                                    ->schema([
                                        TextEntry::make('repository_url')
                                            ->label('Repository')
                                            ->copyable(),
                                        TextEntry::make('default_branch')
                                            ->label('Default branch'),
                                        TextEntry::make('deploy_path')
                                            ->label('Deploy path'),
                                        TextEntry::make('local_source_path')
                                            ->label('Local source path')
                                            ->copyable(),
                                        TextEntry::make('php_version')
                                            ->label('PHP version'),
                                        TextEntry::make('web_root')
                                            ->label('Web root'),
                                        TextEntry::make('deploy_source')
                                            ->label('Deploy source')
                                            ->badge(),
                                        TextEntry::make('active')
                                            ->label('Active')
                                            ->badge(),
                                        TextEntry::make('last_deployed_at')
                                            ->label('Last deployed')
                                            ->dateTime(),
                                        TextEntry::make('health_check_endpoint')
                                            ->label('Health check path')
                                            ->placeholder('None')
                                            ->prefix('GET ')
                                            ->copyable(),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('Domains')
                            ->badge(fn ($record): string => $record->domain_status === 'ready' ? 'Ready' : 'Setup')
                            ->badgeColor(fn ($record): string => $record->domain_status === 'ready' ? 'success' : 'warning')
                            ->schema([
                                Section::make('Domain Mirror')
                                    ->description('This mirrors the live server configuration for all hostnames mapped to this site.')
                                    ->schema([
                                        View::make('filament.sites.domain-management-mirror')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'preview' => $record->domain_preview,
                                            ]),
                                        View::make('filament.sites.dns-preview')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'preview' => $record->dns_preview,
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Inventory')
                            ->badge(fn ($record): string => $record->live_configuration_status === 'synced' ? 'Live' : 'Sync')
                            ->badgeColor(fn ($record): string => match ($record->live_configuration_status) {
                                'synced' => 'success',
                                'error' => 'danger',
                                default => 'warning',
                            })
                            ->schema([
                                Section::make('Live inventory')
                                    ->schema([
                                        View::make('filament.sites.inventory-preview')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'preview' => $record->live_configuration_preview,
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Runtime')
                            ->badge(fn ($record): string => $record->shared_env_mode === 'custom' ? 'Custom' : 'Generated')
                            ->badgeColor(fn ($record): string => $record->shared_env_mode === 'custom' ? 'warning' : 'success')
                            ->schema([
                                Section::make('Runtime configuration')
                                    ->schema([
                                        TextEntry::make('shared_env_mode')
                                            ->label('Shared .env')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'custom' ? 'warning' : 'success'),
                                        TextEntry::make('shared_env_summary')
                                            ->label('Summary')
                                            ->columnSpanFull(),
                                        TextEntry::make('current_release_status')
                                            ->label('Current release status')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                                        TextEntry::make('last_successful_deploy_badge')
                                            ->label('Last successful deploy')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'Never successful' ? 'gray' : 'success'),
                                        TextEntry::make('shared_env_contents')
                                            ->label('Shared .env override')
                                            ->visible(fn ($record): bool => filled($record->shared_env_contents))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('History')
                            ->badge(fn ($record): string => (string) count($record->recent_admin_deployments))
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Release history')
                                    ->schema([
                                        View::make('filament.sites.release-history')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Backups')
                            ->badge(fn ($record): string => $record->backup_status_badge)
                            ->badgeColor(fn ($record): string => match ($record->backup_status) {
                                'healthy' => 'success',
                                'needs attention' => 'warning',
                                'running' => 'info',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Backup actions')
                                    ->schema([
                                        View::make('filament.sites.backup-actions')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Backup status')
                                    ->schema([
                                        View::make('filament.sites.backup-overview')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Backup history')
                                    ->schema([
                                        View::make('filament.sites.backup-history')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Webhooks')
                            ->badge(fn ($record): string => $record->github_webhook_status === 'provisioned' ? 'Healthy' : 'Needs sync')
                            ->badgeColor(fn ($record): string => match ($record->github_webhook_status) {
                                'provisioned' => 'success',
                                'needs-sync' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Webhook health')
                                    ->schema([
                                        TextEntry::make('webhook_secret_state')
                                            ->label('Secret')
                                            ->state(fn ($record): string => filled($record->webhook_secret) ? 'configured' : 'missing')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'configured' ? 'success' : 'danger'),
                                        TextEntry::make('github_webhook_sync_health')
                                            ->label('Sync health')
                                            ->state(fn ($record): string => match (true) {
                                                filled($record->github_webhook_last_error) => 'degraded',
                                                $record->github_webhook_status === 'provisioned' => 'healthy',
                                                $record->github_webhook_status === 'needs-sync' => 'pending sync',
                                                default => 'disconnected',
                                            })
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'healthy' => 'success',
                                                'pending sync' => 'warning',
                                                'degraded' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('github_webhook_status')
                                            ->label('Remote webhook')
                                            ->state(fn ($record): string => match ($record->github_webhook_status) {
                                                'provisioned' => 'provisioned',
                                                'needs-sync' => 'needs sync',
                                                'failed' => 'failed',
                                                default => 'unprovisioned',
                                            })
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'provisioned' => 'success',
                                                'needs-sync' => 'warning',
                                                'failed' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('github_webhook_health')
                                            ->label('Health')
                                            ->state(fn ($record): string => match (true) {
                                                filled($record->github_webhook_last_error) => 'degraded',
                                                $record->github_webhook_status === 'provisioned' => 'healthy',
                                                $record->github_webhook_status === 'needs-sync' => 'needs sync',
                                                default => 'disconnected',
                                            })
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'healthy' => 'success',
                                                'needs sync' => 'warning',
                                                'degraded' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('github_webhook_id')
                                            ->label('Webhook ID'),
                                        TextEntry::make('github_webhook_synced_at')
                                            ->label('Last sync')
                                            ->dateTime(),
                                        TextEntry::make('github_webhook_last_error')
                                            ->label('Last error')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ]);
    }
}
