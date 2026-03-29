<?php

namespace App\Filament\Widgets;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeploymentOverviewStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Servers', Server::query()->count())
                ->description('Managed servers')
                ->icon('heroicon-o-server-stack')
                ->color('primary'),
            Stat::make('Sites', Site::query()->count())
                ->description('Deployable apps')
                ->icon('heroicon-o-globe-alt')
                ->color('info'),
            Stat::make('Successful Deploys', Deployment::query()->where('status', 'successful')->count())
                ->description('All-time successes')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Failed Deploys', Deployment::query()->where('status', 'failed')->count())
                ->description('Needs attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
