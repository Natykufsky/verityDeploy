<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SiteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site overview')
                    ->schema([
                        View::make('filament.sites.site-overview')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Site details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('primary_domain')
                            ->label('Primary domain'),
                        TextEntry::make('deploy_path')
                            ->label('Deploy path'),
                        TextEntry::make('deploy_source')
                            ->label('Deploy source')
                            ->badge(),
                        TextEntry::make('current_release_status')
                            ->label('Current release status')
                            ->badge(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
                Section::make('Domain configuration')
                    ->schema([
                        View::make('filament.sites.ssl-preview')
                            ->columnSpanFull()
                            ->viewData(fn ($record): array => [
                                'preview' => $record->ssl_preview,
                            ]),
                        View::make('filament.sites.domain-preview')
                            ->columnSpanFull()
                            ->viewData(fn ($record): array => [
                                'preview' => $record->domain_preview,
                            ]),
                        View::make('filament.sites.vhost-preview')
                            ->columnSpanFull()
                            ->viewData(fn ($record): array => [
                                'preview' => $record->vhost_preview,
                            ]),
                        View::make('filament.sites.dns-preview')
                            ->columnSpanFull()
                            ->viewData(fn ($record): array => [
                                'preview' => $record->dns_preview,
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Backup actions')
                    ->schema([
                        View::make('filament.sites.backup-actions')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Release history')
                    ->schema([
                        View::make('filament.sites.release-history')
                            ->columnSpanFull(),
                    ]),
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
            ]);
    }
}
