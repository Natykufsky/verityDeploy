<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Deployment\ReleaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CleanupStaleReleases implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(ReleaseManager $releaseManager): void
    {
        Site::query()
            ->whereNotNull('deploy_path')
            ->chunkById(50, function ($sites) use ($releaseManager): void {
                foreach ($sites as $site) {
                    try {
                        $releaseManager->cleanupOldReleases($site->fresh(['server']));
                    } catch (Throwable) {
                        continue;
                    }
                }
            });
    }
}
