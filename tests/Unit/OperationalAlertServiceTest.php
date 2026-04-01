<?php

namespace Tests\Unit;

use App\Jobs\DeliverOperationalAlertEmail;
use App\Jobs\DeliverOperationalAlertWebhooks;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Alerts\OperationalAlertService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OperationalAlertServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notify_all_creates_database_notifications(): void
    {
        $user = User::query()->create([
            'name' => 'Alert User',
            'email' => 'alerts@example.com',
            'password' => bcrypt('password'),
        ]);

        app(OperationalAlertService::class)->notifyAll(
            'Deploy failed',
            'The deployment failed and needs attention.',
            'danger',
            null,
            ['deployment_id' => 123],
        );

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $user->id)
            ->firstOrFail();

        $this->assertSame($user->id, $notification->notifiable_id);
        $this->assertSame('Deploy failed', $notification->data['title']);
        $this->assertSame('The deployment failed and needs attention.', $notification->data['body']);
        $this->assertSame('danger', $notification->data['level']);
        $this->assertSame(123, $notification->data['context']['deployment_id']);
    }

    public function test_notify_all_dispatches_webhook_delivery_jobs_when_enabled(): void
    {
        $user = User::query()->create([
            'name' => 'Webhook User',
            'email' => 'webhook@example.com',
            'password' => bcrypt('password'),
        ]);

        AppSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'alert_webhooks_enabled' => true,
            'alert_webhook_urls' => 'https://example.com/webhooks/veritydeploy',
            'alert_webhook_secret' => 'shared-secret',
        ]);

        Bus::fake();

        app(OperationalAlertService::class)->notifyAll(
            'Deploy failed',
            'The deployment failed and needs attention.',
            'danger',
            null,
            ['deployment_id' => 123],
        );

        Bus::assertDispatched(DeliverOperationalAlertWebhooks::class, function (DeliverOperationalAlertWebhooks $job): bool {
            return $job->payload['title'] === 'Deploy failed'
                && $job->payload['level'] === 'danger'
                && $job->payload['context']['deployment_id'] === 123;
        });

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_notify_all_dispatches_email_delivery_jobs_when_user_preferences_allow_it(): void
    {
        $user = User::query()->create([
            'name' => 'Email User',
            'email' => 'email@example.com',
            'password' => bcrypt('password'),
            'alert_inbox_enabled' => true,
            'alert_email_enabled' => true,
            'alert_minimum_level' => 'warning',
        ]);

        AppSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'alert_email_enabled' => true,
        ]);

        Bus::fake();

        app(OperationalAlertService::class)->notifyAll(
            'Deployment failed',
            'The deployment failed and needs attention.',
            'danger',
            null,
            ['deployment_id' => 123],
        );

        Bus::assertDispatched(DeliverOperationalAlertEmail::class, function (DeliverOperationalAlertEmail $job): bool {
            return $job->payload['title'] === 'Deployment failed'
                && $job->payload['level'] === 'danger';
        });

        $this->assertSame(1, $user->notifications()->count());
    }
}
