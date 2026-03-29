<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\ReleaseCleanupRun;
use Filament\Widgets\Widget;

class ReleaseCleanupOverviewCard extends Widget
{
    protected string $view = 'filament.widgets.release-cleanup-overview-card';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestRun = ReleaseCleanupRun::query()
            ->with(['site'])
            ->latest('started_at')
            ->first();

        return [
            'latestRun' => $latestRun,
            'totalRuns' => ReleaseCleanupRun::query()->count(),
            'successfulRuns' => ReleaseCleanupRun::query()->where('status', 'successful')->count(),
            'failedRuns' => ReleaseCleanupRun::query()->where('status', 'failed')->count(),
            'runningRuns' => ReleaseCleanupRun::query()->where('status', 'running')->count(),
            'latestRunLabel' => $this->latestRunLabel($latestRun),
            'latestRunTone' => $this->latestRunTone($latestRun),
            'latestRunWhen' => $this->latestRunWhen($latestRun),
            'latestRunSummary' => $this->latestRunSummary($latestRun),
            'latestRunUrl' => $this->latestRunUrl($latestRun),
        ];
    }

    public function openLatestRun()
    {
        $latestRun = ReleaseCleanupRun::query()->with(['site'])->latest('started_at')->first();

        return redirect()->to($this->latestRunUrl($latestRun) ?? SiteResource::getUrl('index'));
    }

    protected function latestRunLabel(?ReleaseCleanupRun $run): string
    {
        if (! $run) {
            return 'No cleanup history yet';
        }

        return $run->site?->name ?? 'Unknown site';
    }

    protected function latestRunTone(?ReleaseCleanupRun $run): string
    {
        if (! $run) {
            return 'slate';
        }

        return match ($run->status) {
            'successful' => 'emerald',
            'failed' => 'rose',
            'running' => 'amber',
            default => 'slate',
        };
    }

    protected function latestRunWhen(?ReleaseCleanupRun $run): string
    {
        return $run?->finished_at?->diffForHumans()
            ?? $run?->started_at?->diffForHumans()
            ?? 'just now';
    }

    protected function latestRunSummary(?ReleaseCleanupRun $run): string
    {
        if (! $run) {
            return 'Release cleanup runs will appear here after the first cleanup job or manual rotation.';
        }

        return trim(sprintf(
            '%s | keep %d | %s',
            str($run->status)->headline()->toString(),
            $run->keep_count,
            filled($run->output) ? str($run->output)->limit(120) : 'No output captured',
        ));
    }

    protected function latestRunUrl(?ReleaseCleanupRun $run): ?string
    {
        if (! $run?->site) {
            return null;
        }

        return SiteResource::getUrl('view', [
            'record' => $run->site,
        ]);
    }
}
