<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Widgets\Widget;

class GithubSyncDriftCard extends Widget
{
    protected string $view = 'filament.widgets.github-sync-drift-card';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestDriftSite = Site::query()
            ->whereIn('github_webhook_status', ['needs-sync', 'failed'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return [
            'totalSites' => Site::query()->count(),
            'provisionedCount' => Site::query()->where('github_webhook_status', 'provisioned')->count(),
            'driftCount' => Site::query()->whereIn('github_webhook_status', ['needs-sync', 'failed'])->count(),
            'failedCount' => Site::query()->where('github_webhook_status', 'failed')->count(),
            'latestDriftSite' => $latestDriftSite,
            'latestDriftLabel' => $this->latestDriftLabel($latestDriftSite),
            'latestDriftTone' => $this->latestDriftTone($latestDriftSite),
            'latestDriftWhen' => $this->latestDriftWhen($latestDriftSite),
            'latestDriftSummary' => $this->latestDriftSummary($latestDriftSite),
            'driftUrl' => SiteResource::getUrl('index', [
                'tableFilters' => [
                    'webhook_sync_issues' => [
                        'isActive' => true,
                    ],
                ],
            ]),
            'latestDriftSiteUrl' => $this->latestDriftSiteUrl($latestDriftSite),
        ];
    }

    public function openDriftedSites()
    {
        return redirect()->to(SiteResource::getUrl('index', [
            'tableFilters' => [
                'webhook_sync_issues' => [
                    'isActive' => true,
                ],
            ],
        ]));
    }

    protected function latestDriftLabel(?Site $site): string
    {
        if (! $site) {
            return 'All webhooks synced';
        }

        return $site->name;
    }

    protected function latestDriftTone(?Site $site): string
    {
        if (! $site) {
            return 'emerald';
        }

        return match ($site->github_webhook_status) {
            'failed' => 'rose',
            'needs-sync' => 'amber',
            default => 'slate',
        };
    }

    protected function latestDriftWhen(?Site $site): string
    {
        return $site?->github_webhook_synced_at?->diffForHumans()
            ?? $site?->updated_at?->diffForHumans()
            ?? 'just now';
    }

    protected function latestDriftSummary(?Site $site): string
    {
        if (! $site) {
            return 'No GitHub webhook drift is currently detected.';
        }

        $parts = [
            'Status: '.str($site->github_webhook_status)->headline(),
            filled($site->github_webhook_last_error) ? 'Error: '.$site->github_webhook_last_error : null,
            filled($site->github_webhook_id) ? 'Webhook ID: '.$site->github_webhook_id : null,
        ];

        return implode(' | ', array_values(array_filter($parts)));
    }

    protected function latestDriftSiteUrl(?Site $site): ?string
    {
        if (! $site) {
            return null;
        }

        return SiteResource::getUrl('webhooks', [
            'record' => $site,
        ]);
    }
}
