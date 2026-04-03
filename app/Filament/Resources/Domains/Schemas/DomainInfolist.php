<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Services\Domains\DomainHealthCheckService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class DomainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Domain tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn ($record): string => ucfirst((string) ($record->type ?: 'domain')))
                            ->badgeColor(fn ($record): string => match ($record->type) {
                                'primary' => 'success',
                                'addon' => 'gray',
                                'alias' => 'warning',
                                'subdomain' => 'info',
                                default => 'slate',
                            })
                            ->schema([
                                Section::make('Domain overview')
                                    ->schema([
                                        View::make('filament.domains.domain-overview')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Domain details')
                                    ->schema([
                                        TextEntry::make('server.name')
                                            ->label('Server')
                                            ->placeholder('Unassigned'),
                                        TextEntry::make('site.name')
                                            ->label('Site')
                                            ->placeholder('No site assigned'),
                                        TextEntry::make('name')
                                            ->label('Domain'),
                                        TextEntry::make('type')
                                            ->label('Type')
                                            ->badge(),
                                        TextEntry::make('web_root')
                                            ->label('cPanel document root')
                                            ->placeholder('Not set'),
                                        TextEntry::make('php_version')
                                            ->label('PHP version')
                                            ->placeholder('Default'),
                                        TextEntry::make('external_id')
                                            ->label('External ID')
                                            ->placeholder('None'),
                                        TextEntry::make('is_active')
                                            ->label('Active')
                                            ->badge(),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                        Tab::make('Mirror')
                            ->badge(fn ($record): string => $record->site?->domain_status === 'ready' ? 'Ready' : 'Setup')
                            ->badgeColor(fn ($record): string => $record->site?->domain_status === 'ready' ? 'success' : 'warning')
                            ->schema([
                                Section::make('Domain mirror')
                                    ->description('This is the site-linked mirror for the domain and its related DNS records.')
                                    ->schema([
                                        View::make('filament.domains.domain-mirror')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'record' => $record,
                                                'preview' => $record->site?->domain_preview ?? [],
                                            ]),
                                        View::make('filament.sites.dns-preview')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'preview' => $record->site?->dns_preview ?? [],
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Health')
                            ->schema([
                                Section::make('Live site health')
                                    ->description('This checks the public URL from the browser’s point of view and shows common server responses in plain language.')
                                    ->schema([
                                        View::make('filament.domains.live-site-health')
                                            ->columnSpanFull()
                                            ->viewData(fn ($record): array => [
                                                'preview' => app(DomainHealthCheckService::class)->preview($record),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
