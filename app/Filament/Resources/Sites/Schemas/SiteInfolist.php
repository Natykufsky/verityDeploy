<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                        TextEntry::make('server.name')
                                            ->label('Server'),
                                        TextEntry::make('name'),
                                        TextEntry::make('repository_url')
                                            ->copyable(),
                                        TextEntry::make('default_branch'),
                                        TextEntry::make('deploy_path')
                                            ->copyable(),
                                        TextEntry::make('local_source_path')
                                            ->label('Local source path')
                                            ->copyable(),
                                        TextEntry::make('php_version'),
                                        TextEntry::make('web_root'),
                                        TextEntry::make('deploy_source')
                                            ->badge(),
                                        TextEntry::make('active')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                        TextEntry::make('last_deployed_at')
                                            ->dateTime(),
                                        TextEntry::make('current_release_status')
                                            ->label('Current release status')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                                        TextEntry::make('last_successful_deploy_badge')
                                            ->label('Last successful deploy')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'Never successful' ? 'gray' : 'success'),
                                    ])
                                    ->columns(2),
                                Section::make('Backup actions')
                                    ->schema([
                                        View::make('filament.sites.backup-actions')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('cPanel deploy status')
                                    ->visible(fn ($record): bool => ($record->server?->connection_type ?? null) === 'cpanel')
                                    ->schema([
                                        TextEntry::make('cpanel_deploy_status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'ready' => 'success',
                                                'needs setup' => 'warning',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('cpanel_deploy_summary')
                                            ->label('Summary')
                                            ->columnSpanFull(),
                                        TextEntry::make('cpanel_deploy_checklist')
                                            ->label('Checklist')
                                            ->state(fn ($record): array => $record->cpanel_deploy_checklist)
                                            ->bulleted()
                                            ->listWithLineBreaks()
                                            ->columnSpanFull(),
                                        TextEntry::make('server.cpanel_api_port')
                                            ->label('API port'),
                                        TextEntry::make('server.status')
                                            ->label('Server status')
                                            ->badge(),
                                        TextEntry::make('current_release_path')
                                            ->label('Current release')
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
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
                                        KeyValueEntry::make('environment_variables')
                                            ->label('Environment variables')
                                            ->keyLabel('Variable')
                                            ->valueLabel('Value'),
                                        TextEntry::make('shared_env_contents')
                                            ->label('Shared .env override')
                                            ->visible(fn ($record): bool => filled($record->shared_env_contents))
                                            ->html()
                                            ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                                            ->columnSpanFull(),
                                        RepeatableEntry::make('shared_files')
                                            ->schema([
                                                TextEntry::make('path')
                                                    ->label('Path')
                                                    ->copyable(),
                                                TextEntry::make('contents')
                                                    ->label('Contents')
                                                    ->html()
                                                    ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->contained(),
                                    ])
                                    ->columns(1),
                            ]),
                        Tab::make('Terminal')
                            ->badge('Live')
                            ->badgeColor('info')
                            ->schema([
                                Section::make('Site terminal')
                                    ->schema([
                                        View::make('filament.sites.terminal-panel')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
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

    protected static function renderTerminalBlock(mixed $state): HtmlString
    {
        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">%s</pre>',
            e((string) $state),
        ));
    }
}
