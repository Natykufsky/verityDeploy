<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ScheduledJobResource;
use App\Models\ScheduledJob;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SchedulerHealthOverviewCard extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activeJobs = $this->activeJobsCount();
        $dueNowJobs = $this->dueNowJobsCount();
        $nextDueJob = $this->nextDueJob();
        $latestRunJob = $this->latestRunJob();

        return [
            Stat::make('Active Schedules', $activeJobs)
                ->description('Enabled scheduled jobs')
                ->icon('heroicon-o-calendar-days')
                ->color($activeJobs > 0 ? 'success' : 'gray')
                ->url(ScheduledJobResource::getUrl('index')),
            Stat::make('Due Now', $dueNowJobs)
                ->description('Jobs waiting for the next scheduler cycle')
                ->icon('heroicon-o-clock')
                ->color($dueNowJobs > 0 ? 'warning' : 'info'),
            Stat::make('Next Run', $this->nextRunLabel($nextDueJob?->next_run_at))
                ->description($this->nextRunSummary($nextDueJob))
                ->icon('heroicon-o-bell-alert')
                ->color($nextDueJob ? 'primary' : 'gray'),
            Stat::make('Last Run', $this->latestRunLabel($latestRunJob?->last_run_at))
                ->description($latestRunJob?->command ?? 'No scheduled runs yet')
                ->icon('heroicon-o-arrow-path')
                ->color($latestRunJob ? 'success' : 'gray'),
        ];
    }

    protected function activeJobsCount(): int
    {
        return ScheduledJob::query()->where('is_active', true)->count();
    }

    protected function dueNowJobsCount(): int
    {
        return ScheduledJob::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->count();
    }

    protected function nextDueJob(): ?ScheduledJob
    {
        return ScheduledJob::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->first();
    }

    protected function latestRunJob(): ?ScheduledJob
    {
        return ScheduledJob::query()
            ->whereNotNull('last_run_at')
            ->orderByDesc('last_run_at')
            ->first();
    }

    protected function nextRunLabel(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'No active schedule';
        }

        return $value->diffForHumans();
    }

    protected function latestRunLabel(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'No runs yet';
        }

        return $value->diffForHumans();
    }

    protected function nextRunSummary(?ScheduledJob $job): string
    {
        if (! $job) {
            return 'No active schedule is due next';
        }

        return sprintf(
            '%s on %s',
            $job->site?->name ?? 'Unknown site',
            $job->next_run_at?->format('M d, H:i') ?? 'unknown time',
        );
    }
}
