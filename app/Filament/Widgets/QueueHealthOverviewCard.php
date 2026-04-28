<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueHealthOverviewCard extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $pendingJobs = $this->pendingJobsCount();
        $reservedJobs = $this->reservedJobsCount();
        $failedJobs = $this->failedJobsCount();
        $latestFailedJob = $this->latestFailedJob();

        return [
            Stat::make('Queued Jobs', $pendingJobs)
                ->description('Waiting to be processed')
                ->icon('heroicon-o-inbox-stack')
                ->color($pendingJobs > 0 ? 'warning' : 'success'),
            Stat::make('Reserved Jobs', $reservedJobs)
                ->description('Currently being worked')
                ->icon('heroicon-o-arrow-path')
                ->color($reservedJobs > 0 ? 'info' : 'gray'),
            Stat::make('Failed Jobs', $failedJobs)
                ->description($this->latestFailedJobSummary($latestFailedJob))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }

    protected function pendingJobsCount(): int
    {
        return DB::table('jobs')->count();
    }

    protected function reservedJobsCount(): int
    {
        return DB::table('jobs')->whereNotNull('reserved_at')->count();
    }

    protected function failedJobsCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    protected function latestFailedJob(): ?object
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->first();
    }

    protected function latestFailedJobSummary(?object $job): string
    {
        if (! $job) {
            return 'No failed jobs recorded yet';
        }

        return trim(sprintf(
            '%s on %s',
            Str::headline(str_replace('_', ' ', (string) $job->queue)),
            $this->humanTimestamp($job->failed_at ?? null),
        ));
    }

    protected function humanTimestamp(mixed $value): string
    {
        if (blank($value)) {
            return 'unknown time';
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return 'unknown time';
        }
    }
}
