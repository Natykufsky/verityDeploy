<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\CpanelWizardRun;
use Filament\Widgets\Widget;

class CpanelSetupCard extends Widget
{
    protected string $view = 'filament.widgets.cpanel-setup-card';

    protected function getViewData(): array
    {
        $serverRun = CpanelWizardRun::query()
            ->with(['server'])
            ->where('wizard_type', 'server_checks')
            ->latest('started_at')
            ->first();

        $siteRun = CpanelWizardRun::query()
            ->with(['site'])
            ->where('wizard_type', 'site_bootstrap')
            ->latest('started_at')
            ->first();

        return [
            'serverRun' => $serverRun,
            'siteRun' => $siteRun,
            'overallState' => $this->overallState($serverRun, $siteRun),
            'latestRunLabel' => $this->runLabel($this->latestRun($serverRun, $siteRun)),
            'latestRunUrl' => $this->runUrl($this->latestRun($serverRun, $siteRun)),
            'serverRunLabel' => $this->runLabel($serverRun),
            'siteRunLabel' => $this->runLabel($siteRun),
            'serverRunUrl' => $this->runUrl($serverRun),
            'siteRunUrl' => $this->runUrl($siteRun),
            'serverRunState' => $this->runState($serverRun),
            'siteRunState' => $this->runState($siteRun),
            'serverRunWhen' => $this->runWhen($serverRun),
            'siteRunWhen' => $this->runWhen($siteRun),
            'serverRunBadge' => $this->runBadge($serverRun),
            'siteRunBadge' => $this->runBadge($siteRun),
            'serverRunTone' => $this->runTone($serverRun),
            'siteRunTone' => $this->runTone($siteRun),
            'auditCountLast24Hours' => $count = CpanelWizardRun::query()
                ->where('started_at', '>=', now()->subDay())
                ->count(),
            'auditCountTone' => $this->auditTone($count),
        ];
    }

    public function openLatestRun()
    {
        $serverRun = CpanelWizardRun::query()
            ->with(['server'])
            ->where('wizard_type', 'server_checks')
            ->latest('started_at')
            ->first();

        $siteRun = CpanelWizardRun::query()
            ->with(['site'])
            ->where('wizard_type', 'site_bootstrap')
            ->latest('started_at')
            ->first();

        $url = $this->runUrl($this->latestRun($serverRun, $siteRun));

        return redirect()->to($url ?? ServerResource::getUrl('index'));
    }

    public function openServerRun()
    {
        $serverRun = CpanelWizardRun::query()
            ->with(['server'])
            ->where('wizard_type', 'server_checks')
            ->latest('started_at')
            ->first();

        return redirect()->to($this->runUrl($serverRun) ?? ServerResource::getUrl('index'));
    }

    public function openSiteRun()
    {
        $siteRun = CpanelWizardRun::query()
            ->with(['site'])
            ->where('wizard_type', 'site_bootstrap')
            ->latest('started_at')
            ->first();

        return redirect()->to($this->runUrl($siteRun) ?? SiteResource::getUrl('index'));
    }

    protected function overallState(?CpanelWizardRun $serverRun, ?CpanelWizardRun $siteRun): string
    {
        if (! $serverRun && ! $siteRun) {
            return 'No runs yet';
        }

        if (($serverRun?->isFailed() ?? false) || ($siteRun?->isFailed() ?? false)) {
            return 'Needs attention';
        }

        if (($serverRun?->isRunning() ?? false) || ($siteRun?->isRunning() ?? false)) {
            return 'In progress';
        }

        if (($serverRun?->isSuccessful() ?? false) && ($siteRun?->isSuccessful() ?? false)) {
            return 'Healthy';
        }

        return 'Partial setup';
    }

    protected function runLabel(?CpanelWizardRun $run): string
    {
        return $run?->wizard_type_label ?? 'No run yet';
    }

    protected function runState(?CpanelWizardRun $run): string
    {
        return $run?->status ?? 'none';
    }

    protected function runBadge(?CpanelWizardRun $run): string
    {
        if (! $run) {
            return 'No run';
        }

        return match ($run->status) {
            'successful' => 'Healthy',
            'failed' => 'Failed',
            'running' => 'Running',
            default => str($run->status)->headline()->toString(),
        };
    }

    protected function runTone(?CpanelWizardRun $run): string
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

    protected function auditTone(int $count): string
    {
        if ($count === 0) {
            return 'slate';
        }

        if ($count < 3) {
            return 'amber';
        }

        return 'emerald';
    }

    protected function runWhen(?CpanelWizardRun $run): string
    {
        if (! $run) {
            return 'never';
        }

        $timestamp = $run->finished_at ?? $run->started_at;

        return $timestamp?->diffForHumans() ?? 'just now';
    }

    protected function runUrl(?CpanelWizardRun $run): ?string
    {
        if (! $run) {
            return null;
        }

        return match ($run->wizard_type) {
            'server_checks' => $run->server ? ServerResource::getUrl('cpanel-wizard', [
                'record' => $run->server,
            ]) : null,
            'site_bootstrap' => $run->site ? SiteResource::getUrl('cpanel-bootstrap-wizard', [
                'record' => $run->site,
            ]) : null,
            default => null,
        };
    }

    protected function latestRun(?CpanelWizardRun $serverRun, ?CpanelWizardRun $siteRun): ?CpanelWizardRun
    {
        return collect([$serverRun, $siteRun])
            ->filter()
            ->sortByDesc(fn (CpanelWizardRun $run): int => $run->started_at?->getTimestamp() ?? 0)
            ->first();
    }
}
