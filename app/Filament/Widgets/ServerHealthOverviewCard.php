<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Servers\ServerResource;
use App\Models\Server;
use App\Models\ServerHealthCheck;
use Filament\Widgets\Widget;

class ServerHealthOverviewCard extends Widget
{
    protected string $view = 'filament.widgets.server-health-overview-card';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestCheck = ServerHealthCheck::query()
            ->with(['server'])
            ->latest('tested_at')
            ->first();

        return [
            'onlineCount' => Server::query()->where('status', 'online')->count(),
            'offlineCount' => Server::query()->where('status', 'offline')->count(),
            'errorCount' => Server::query()->where('status', 'error')->count(),
            'totalCount' => Server::query()->count(),
            'latestCheck' => $latestCheck,
            'latestCheckLabel' => $this->latestCheckLabel($latestCheck),
            'latestCheckTone' => $this->latestCheckTone($latestCheck),
            'latestCheckWhen' => $this->latestCheckWhen($latestCheck),
            'latestCheckSummary' => $this->latestCheckSummary($latestCheck),
            'latestCheckUrl' => $this->latestCheckUrl($latestCheck),
            'serverIndexUrl' => ServerResource::getUrl('index'),
        ];
    }

    public function openServers()
    {
        return redirect()->to(ServerResource::getUrl('index'));
    }

    protected function latestCheckLabel(?ServerHealthCheck $check): string
    {
        if (! $check) {
            return 'No health checks yet';
        }

        return $check->server?->name ?? 'Unknown server';
    }

    protected function latestCheckTone(?ServerHealthCheck $check): string
    {
        if (! $check) {
            return 'slate';
        }

        return match ($check->status) {
            'successful' => 'emerald',
            'failed' => 'rose',
            'running' => 'amber',
            default => 'slate',
        };
    }

    protected function latestCheckWhen(?ServerHealthCheck $check): string
    {
        return $check?->tested_at?->diffForHumans() ?? 'just now';
    }

    protected function latestCheckSummary(?ServerHealthCheck $check): string
    {
        if (! $check) {
            return 'Health checks will appear here after the first scheduled run.';
        }

        $metrics = $check->metrics ?? [];

        return trim(sprintf(
            '%s | CPU %s | RAM %s | Disk %s',
            str($check->status)->headline()->toString(),
            data_get($metrics, 'cpu_usage', 'n/a'),
            data_get($metrics, 'ram_usage', 'n/a'),
            data_get($metrics, 'disk_free', 'n/a'),
        ));
    }

    protected function latestCheckUrl(?ServerHealthCheck $check): ?string
    {
        if (! $check?->server) {
            return null;
        }

        return ServerResource::getUrl('view', [
            'record' => $check->server,
        ]);
    }
}
