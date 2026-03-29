<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Alerts\OperationalAlertService;
use App\Services\GitHub\WebhookProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshWebhookStatuses implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(WebhookProvisioner $webhookProvisioner): void
    {
        Site::query()
            ->whereNotNull('repository_url')
            ->chunkById(50, function ($sites) use ($webhookProvisioner): void {
                foreach ($sites as $site) {
                    $previousStatus = $site->github_webhook_status;

                    try {
                        $result = $webhookProvisioner->refreshStatus($site->fresh());
                        $status = (string) data_get($result, 'status', $site->fresh()?->github_webhook_status);

                        if ($previousStatus !== $status && in_array($status, ['needs-sync', 'failed'], true)) {
                            app(OperationalAlertService::class)->webhookDrift(
                                $site->fresh(),
                                $status === 'needs-sync'
                                    ? 'GitHub no longer has the expected webhook. Re-provision it to restore push deploys.'
                                    : 'Webhook status refresh failed. Check GitHub access and retry the sync.',
                            );
                        }
                    } catch (Throwable $throwable) {
                        $site->update([
                            'github_webhook_status' => 'failed',
                            'github_webhook_last_error' => $throwable->getMessage(),
                            'github_webhook_synced_at' => now(),
                        ]);

                        app(OperationalAlertService::class)->webhookDrift(
                            $site->fresh(),
                            $throwable->getMessage(),
                        );
                    }
                }
            });
    }
}
