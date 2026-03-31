<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SiteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Details')
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
                Section::make('cPanel Deploy Status')
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
                Section::make('Webhook Health')
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
                Section::make('Runtime Configuration')
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
                Section::make('Release History')
                    ->schema([
                        RepeatableEntry::make('recent_admin_deployments')
                            ->schema([
                                TextEntry::make('source')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'successful' => 'success',
                                        'running' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('branch'),
                                TextEntry::make('commit_hash')
                                    ->label('Commit')
                                    ->copyable(),
                                TextEntry::make('release_path')
                                    ->label('Release path')
                                    ->copyable()
                                    ->columnSpanFull(),
                                TextEntry::make('started_at')
                                    ->dateTime(),
                                TextEntry::make('finished_at')
                                    ->dateTime(),
                                TextEntry::make('error_message')
                                    ->columnSpanFull(),
                                TextEntry::make('output')
                                    ->label('Log output')
                                    ->html()
                                    ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->contained(),
                    ]),
                Section::make('Backup Status')
                    ->schema([
                        TextEntry::make('backup_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'healthy' => 'success',
                                'needs attention' => 'warning',
                                'running' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('latest_backup_summary')
                            ->label('Latest backup')
                            ->columnSpanFull(),
                        TextEntry::make('latest_backup_snapshot_path')
                            ->label('Latest snapshot path')
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Backup History')
                    ->schema([
                        RepeatableEntry::make('recent_admin_backups')
                            ->schema([
                                TextEntry::make('operation')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'successful' => 'success',
                                        'running' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('snapshot_path')
                                    ->label('Snapshot path')
                                    ->copyable()
                                    ->columnSpanFull(),
                                TextEntry::make('restored_release_path')
                                    ->label('Restored release')
                                    ->copyable()
                                    ->columnSpanFull(),
                                TextEntry::make('started_at')
                                    ->dateTime(),
                                TextEntry::make('finished_at')
                                    ->dateTime(),
                                TextEntry::make('error_message')
                                    ->columnSpanFull(),
                                TextEntry::make('output')
                                    ->label('Output')
                                    ->html()
                                    ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->contained(),
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
