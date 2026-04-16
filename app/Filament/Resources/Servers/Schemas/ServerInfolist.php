<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Services\ServerMetrics\ServerMetricsBridgeUrl;
use App\Services\Servers\ServerDomainSynchronizer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ServerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Server tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => ucfirst((string) $record->status))
                            ->badgeColor(fn ($record): string => match ($record->status) {
                                'online' => 'success',
                                'offline' => 'gray',
                                'error' => 'danger',
                                default => 'warning',
                            })
                            ->schema([
                                Section::make('Server overview')
                                    ->schema([
                                        View::make('filament.servers.server-overview')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Core details')
                                    ->schema([
                                        TextEntry::make('owner.name')
                                            ->label('Owner')
                                            ->placeholder('Unassigned'),
                                        TextEntry::make('team.name')
                                            ->label('Team')
                                            ->placeholder('No team assigned'),
                                        TextEntry::make('ip_address')
                                            ->label('IP address')
                                            ->copyable(),
                                        TextEntry::make('ssh_user')
                                            ->label('SSH user'),
                                        TextEntry::make('ssh_port')
                                            ->label('SSH port'),
                                        TextEntry::make('cpanel_username')
                                            ->label('cPanel username')
                                            ->placeholder('Same as SSH user if not set')
                                            ->copyable(),
                                        TextEntry::make('provider_reference')
                                            ->label('Provider reference')
                                            ->copyable(),
                                        TextEntry::make('provider_region')
                                            ->label('Provider region'),
                                        TextEntry::make('vhost_config_path')
                                            ->label('Vhost config path')
                                            ->placeholder('Auto-derived from provider settings'),
                                        TextEntry::make('vhost_enabled_path')
                                            ->label('Vhost enabled path')
                                            ->placeholder('Auto-derived from provider settings'),
                                        TextEntry::make('vhost_reload_command')
                                            ->label('Reload command')
                                            ->placeholder('Auto-derived from provider settings'),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Sites')
                            ->badge(fn ($record): string => (string) $record->sites()->count())
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Sites on this server')
                                    ->description('Sites deployed and managed on this server.')
                                    ->schema([
                                        View::make('filament.servers.server-sites')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Live Domains')
                            ->badge(fn ($record): string => (string) $record->domains()->count())
                            ->badgeColor(fn ($record): string => $record->domains()->count() > 0 ? 'info' : 'gray')
                            ->schema([
                                Section::make('Live inventory')
                                    ->description('This is the live cPanel-backed inventory for domains managed on this server.')
                                    ->schema([
                                        View::make('filament.servers.inventory-preview')
                                            ->columnSpanFull()
                                            ->viewData(function ($record): array {
                                                $record = $record->load([
                                                    'domains.site.currentDomain',
                                                ]);
                                                $preview = app(ServerDomainSynchronizer::class)->preview($record->fresh());
                                                $localDomains = $record->domains->keyBy(fn ($domain): string => strtolower(trim((string) $domain->name)));

                                                $domains = collect((array) ($preview['domains'] ?? []))
                                                    ->map(function (array $domain) use ($localDomains): array {
                                                        $name = trim((string) ($domain['domain'] ?? ''));
                                                        $localDomain = $localDomains->get(strtolower($name));
                                                        $site = $localDomain?->site;
                                                        $liveDomain = $site?->currentDomain?->name
                                                            ?? $site?->primary_domain
                                                            ?? $name;
                                                        $scheme = ($site?->force_https || in_array((string) ($site?->ssl_state ?? ''), ['valid', 'issued', 'active', 'installed'], true)) ? 'https' : 'http';

                                                        return [
                                                            'id' => $localDomain?->id,
                                                            'name' => $name,
                                                            'type' => (string) ($domain['type'] ?? 'domain'),
                                                            'site_name' => $site?->name,
                                                            'site_id' => $localDomain?->site_id,
                                                            'document_root' => $domain['documentroot']
                                                                ?? $domain['document_root']
                                                                ?? null,
                                                            'ssl_status' => $domain['ssl_status'] ?? null,
                                                            'ssl_expires_at' => $domain['ssl_expires_at'] ?? null,
                                                            'is_ssl_enabled' => (bool) ($domain['is_ssl_enabled'] ?? false),
                                                            'is_active' => $localDomain?->is_active ?? true,
                                                            'external_id' => $domain['user'] ?? $localDomain?->external_id,
                                                            'live_url' => filled($liveDomain) ? sprintf('%s://%s', $scheme, $liveDomain) : null,
                                                        ];
                                                    })
                                                    ->filter(fn (array $domain): bool => filled($domain['name'] ?? null))
                                                    ->values()
                                                    ->all();

                                                if ($domains === []) {
                                                    $domains = $record->domains
                                                        ->map(function ($domain): array {
                                                            $site = $domain->site;
                                                            $liveDomain = $site?->currentDomain?->name
                                                                ?? $site?->primary_domain
                                                                ?? $domain->name;
                                                            $scheme = ($site?->force_https || in_array((string) ($site?->ssl_state ?? ''), ['valid', 'issued', 'active', 'installed'], true)) ? 'https' : 'http';

                                                            return [
                                                                'id' => $domain->id,
                                                                'name' => $domain->name,
                                                                'type' => $domain->type,
                                                                'site_name' => $site?->name,
                                                                'site_id' => $domain->site_id,
                                                                'document_root' => null,
                                                                'ssl_status' => $domain->ssl_status,
                                                                'ssl_expires_at' => $domain->ssl_expires_at,
                                                                'is_ssl_enabled' => (bool) $domain->is_ssl_enabled,
                                                                'is_active' => (bool) $domain->is_active,
                                                                'external_id' => $domain->external_id,
                                                                'live_url' => filled($liveDomain) ? sprintf('%s://%s', $scheme, $liveDomain) : null,
                                                            ];
                                                        })
                                                        ->values()
                                                        ->all();
                                                }

                                                return [
                                                    'record' => $record,
                                                    'preview' => $preview,
                                                    'domains' => $domains,
                                                ];
                                            }),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Metrics')
                            ->badge('Live')
                            ->badgeColor('success')
                            ->schema([
                                Section::make('Health metrics')
                                    ->schema([
                                        View::make('filament.servers.server-metrics')
                                            ->viewData(fn ($record): array => [
                                                'record' => $record->fresh(),
                                                'bridge' => app(ServerMetricsBridgeUrl::class)->make($record->fresh()),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('History')
                            ->badge(fn ($record): string => (string) count($record->connectionTests))
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Operational timeline')
                                    ->schema([
                                        View::make('filament.schemas.components.operational-timeline'),
                                    ]),
                                Section::make('Recent connection checks')
                                    ->schema([
                                        View::make('filament.schemas.components.connection-tests')
                                            ->viewData(fn ($record): array => [
                                                'record' => $record->load([
                                                    'connectionTests' => fn ($query) => $query->latest('tested_at')->latest()->limit(5),
                                                ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
