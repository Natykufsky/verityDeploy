<?php

namespace Tests\Unit;

use App\Jobs\DeliverOperationalAlertEmail;
use App\Jobs\DeliverOperationalAlertWebhooks;
use App\Models\AppSetting;
use App\Models\Server;
use App\Models\Site;
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

    public function test_site_ssl_alert_helpers_create_contextual_notifications(): void
    {
        $user = User::query()->create([
            'name' => 'SSL User',
            'email' => 'ssl@example.com',
            'password' => bcrypt('password'),
            'alert_inbox_enabled' => true,
            'alert_minimum_level' => 'success',
        ]);

        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
            'ssl_state' => 'valid',
            'force_https' => true,
        ]);

        app(OperationalAlertService::class)->siteSslRefreshed(
            $site,
            'Triggered an AutoSSL check for verityapi.monaksoft.com.ng.',
        );

        app(OperationalAlertService::class)->siteHttpsRedirectSynced(
            $site,
            'Enabled HTTPS redirects for verityapi.monaksoft.com.ng.',
        );

        app(OperationalAlertService::class)->siteSslActionFailed(
            $site,
            'SSL refresh',
            'AutoSSL failed on cPanel.',
        );

        $notifications = $user->notifications()->latest()->get();

        $this->assertCount(3, $notifications);
        $titles = $notifications->pluck('data.title')->all();
        $bodies = $notifications->pluck('data.body')->all();

        $this->assertContains('SSL refresh failed: verityapi', $titles);
        $this->assertContains('HTTPS redirect synced: verityapi', $titles);
        $this->assertContains('SSL refreshed: verityapi', $titles);
        $this->assertContains('AutoSSL failed on cPanel.', $bodies);
        $this->assertContains('Enabled HTTPS redirects for verityapi.monaksoft.com.ng.', $bodies);
        $this->assertContains('Triggered an AutoSSL check for verityapi.monaksoft.com.ng.', $bodies);
    }
}
