<?php

namespace Tests\Unit;

use App\Jobs\DeliverOperationalAlertWebhooks;
use App\Models\AppSetting;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverOperationalAlertWebhooksTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_posts_alert_payloads_to_configured_webhooks(): void
    {
        AppSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'alert_webhooks_enabled' => true,
            'alert_webhook_urls' => "https://example.com/webhooks/veritydeploy\nhttps://backup.example.com/alerts",
            'alert_webhook_secret' => 'shared-secret',
        ]);

        Http::fake();

        $job = new DeliverOperationalAlertWebhooks([
            'title' => 'Deploy failed',
            'body' => 'The deployment failed and needs attention.',
            'level' => 'danger',
            'url' => '/admin/deployments/1',
            'context' => [
                'deployment_id' => 123,
            ],
        ]);

        $job->handle(app(AppSettings::class));

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://example.com/webhooks/veritydeploy'
                && $request->header('X-VerityDeploy-Event')[0] === 'operational.alert'
                && $request->header('X-VerityDeploy-Level')[0] === 'danger'
                && filled($request->header('X-VerityDeploy-Signature')[0] ?? null)
                && $data['title'] === 'Deploy failed';
        });
    }
}
