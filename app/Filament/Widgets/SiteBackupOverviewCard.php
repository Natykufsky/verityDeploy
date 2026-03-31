<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\SiteBackup;
use Filament\Widgets\Widget;

class SiteBackupOverviewCard extends Widget
{
    protected string $view = 'filament.widgets.site-backup-overview-card';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestBackup = SiteBackup::query()
            ->with(['site'])
            ->where('operation', 'backup')
            ->latest('started_at')
            ->first();

        return [
            'latestBackup' => $latestBackup,
            'totalBackups' => SiteBackup::query()->where('operation', 'backup')->count(),
            'successfulBackups' => SiteBackup::query()->where('operation', 'backup')->where('status', 'successful')->count(),
            'failedBackups' => SiteBackup::query()->where('operation', 'backup')->where('status', 'failed')->count(),
            'runningBackups' => SiteBackup::query()->where('operation', 'backup')->where('status', 'running')->count(),
            'latestBackupLabel' => $this->latestBackupLabel($latestBackup),
            'latestBackupTone' => $this->latestBackupTone($latestBackup),
            'latestBackupWhen' => $this->latestBackupWhen($latestBackup),
            'latestBackupSummary' => $this->latestBackupSummary($latestBackup),
            'latestBackupUrl' => $this->latestBackupUrl($latestBackup),
            'sitesIndexUrl' => SiteResource::getUrl('index'),
        ];
    }

    public function openLatestRun()
    {
        $latestBackup = SiteBackup::query()
            ->with(['site'])
            ->where('operation', 'backup')
            ->latest('started_at')
            ->first();

        return redirect()->to($this->latestBackupUrl($latestBackup) ?? SiteResource::getUrl('index'));
    }

    protected function latestBackupLabel(?SiteBackup $backup): string
    {
        if (! $backup) {
            return 'No backup history yet';
        }

        return $backup->site?->name ?? 'Unknown site';
    }

    protected function latestBackupTone(?SiteBackup $backup): string
    {
        if (! $backup) {
            return 'slate';
        }

        return match ($backup->status) {
            'successful' => 'emerald',
            'failed' => 'rose',
            'running' => 'amber',
            default => 'slate',
        };
    }

    protected function latestBackupWhen(?SiteBackup $backup): string
    {
        return $backup?->finished_at?->diffForHumans()
            ?? $backup?->started_at?->diffForHumans()
            ?? 'just now';
    }

    protected function latestBackupSummary(?SiteBackup $backup): string
    {
        if (! $backup) {
            return 'Backups will appear here after the first manual backup or scheduled snapshot.';
        }

        return trim(sprintf(
            '%s | %s | %s',
            str($backup->status)->headline()->toString(),
            $backup->snapshot_path ?? 'No snapshot path recorded',
            filled($backup->checksum) ? str($backup->checksum)->limit(16) : 'No checksum recorded',
        ));
    }

    protected function latestBackupUrl(?SiteBackup $backup): ?string
    {
        if (! $backup?->site) {
            return null;
        }

        return SiteResource::getUrl('view', [
            'record' => $backup->site,
        ]);
    }
}
