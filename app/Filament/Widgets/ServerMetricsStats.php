<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServerMetricsStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    public ?int $recordId = null;

    public ?Server $record = null;

    public function mount(): void
    {
        if ($this->recordId) {
            $this->record = Server::query()->find($this->recordId);
        }
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $record = $this->recordId ? Server::query()->find($this->recordId) : $this->record;
        $metrics = $record?->metrics ?? [];
        $cpu = data_get($metrics, 'cpu_usage');
        $ram = data_get($metrics, 'ram_usage');
        $disk = data_get($metrics, 'disk_free');
        $uptime = data_get($metrics, 'uptime') ?? 'Unknown';

        return [
            Stat::make('CPU Load', is_null($cpu) ? 'n/a' : $cpu)
                ->description('1 minute load average')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary'),
            Stat::make('RAM Usage', is_null($ram) ? 'n/a' : $ram)
                ->description('Current memory pressure')
                ->icon('heroicon-o-circle-stack')
                ->color('success'),
            Stat::make('Disk Free', is_null($disk) ? 'n/a' : $disk)
                ->description('Root filesystem free space')
                ->icon('heroicon-o-circle-stack')
                ->color('warning'),
            Stat::make('Uptime', $uptime)
                ->description('Latest health check')
                ->icon('heroicon-o-clock')
                ->color('gray'),
        ];
    }
}
