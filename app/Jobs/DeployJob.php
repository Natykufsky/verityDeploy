<?php

namespace App\Jobs;

use App\Actions\DeployProject;
use App\Models\Deployment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeployJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $deploymentId,
    ) {}

    public function handle(DeployProject $deployProject): void
    {
        $deployment = Deployment::query()->findOrFail($this->deploymentId);

        $deployProject->run($deployment);
    }

    public function failed(Throwable $throwable): void
    {
        $deployment = Deployment::query()->find($this->deploymentId);

        if (! $deployment || $deployment->status === 'failed') {
            return;
        }

        $deployment->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $throwable->getMessage(),
        ]);
    }
}
