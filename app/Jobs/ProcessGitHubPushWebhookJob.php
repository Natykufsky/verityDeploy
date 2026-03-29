<?php

namespace App\Jobs;

use App\Actions\DeployProject;
use App\Models\Site;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessGitHubPushWebhookJob extends ProcessWebhookJob
{
    public function handle(DeployProject $deployProject): void
    {
        $payload = $this->webhookCall->payload ?? [];
        $repository = (array) data_get($payload, 'repository', []);
        $branchRef = data_get($payload, 'ref');
        $commitHash = data_get($payload, 'after');
        $branch = $this->extractBranchName(is_string($branchRef) ? $branchRef : null);

        $sites = Site::query()
            ->where('active', true)
            ->whereNotNull('repository_url')
            ->with('server')
            ->get()
            ->filter(function (Site $site) use ($repository, $branchRef): bool {
                if ($branchRef !== ('refs/heads/'.$site->default_branch)) {
                    return false;
                }

                $candidateUrls = array_filter([
                    data_get($repository, 'clone_url'),
                    data_get($repository, 'git_url'),
                    data_get($repository, 'html_url'),
                ]);

                return in_array(
                    $this->normalizeRepositoryUrl($site->repository_url),
                    array_map(fn (string $url): string => $this->normalizeRepositoryUrl($url), $candidateUrls),
                    true
                );
            });

        foreach ($sites as $site) {
            $deployProject->dispatch(
                $site,
                null,
                'git',
                is_string($commitHash) ? $commitHash : null,
                $branch
            );
        }
    }

    protected function extractBranchName(?string $branchRef): ?string
    {
        if (blank($branchRef)) {
            return null;
        }

        if (! str_starts_with($branchRef, 'refs/heads/')) {
            return $branchRef;
        }

        return Str::after($branchRef, 'refs/heads/');
    }

    protected function normalizeRepositoryUrl(string $url): string
    {
        $normalized = Str::of($url)->trim()->lower()->toString();
        $normalized = preg_replace('/\.git$/', '', $normalized) ?? $normalized;

        return rtrim($normalized, '/');
    }
}
