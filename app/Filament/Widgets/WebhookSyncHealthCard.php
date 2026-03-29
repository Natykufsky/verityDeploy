<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Filament\Resources\Sites\SiteResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WebhookSyncHealthCard extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $total = Site::query()->count();
        $synced = Site::query()->where('github_webhook_status', 'provisioned')->count();
        $needsSync = Site::query()->where('github_webhook_status', 'needs-sync')->count();
        $failed = Site::query()->where('github_webhook_status', 'failed')->count();
        $healthyRatio = $total > 0 ? (int) round(($synced / $total) * 100) : 100;

        $color = match (true) {
            $failed > 0 => 'danger',
            $needsSync > 0 => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Webhook Sync Health', "{$synced}/{$total}")
                ->description("{$healthyRatio}% healthy, {$needsSync} need sync, {$failed} failed")
                ->icon('heroicon-o-link')
                ->color($color)
                ->url(SiteResource::getUrl('index', [
                    'tableFilters' => [
                        'webhook_sync_issues' => [
                            'isActive' => true,
                        ],
                    ],
                ])),
        ];
    }
}
