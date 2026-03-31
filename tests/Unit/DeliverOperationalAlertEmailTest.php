<?php

namespace Tests\Unit;

use App\Jobs\DeliverOperationalAlertEmail;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliverOperationalAlertEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sends_email_alerts_and_logs_delivery_status(): void
    {
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

        $user = User::query()->create([
            'name' => 'Alert User',
            'email' => 'alerts@example.com',
            'password' => bcrypt('password'),
            'alert_email_enabled' => true,
            'alert_inbox_enabled' => true,
            'alert_minimum_level' => 'warning',
        ]);

        Mail::fake();

        app(DeliverOperationalAlertEmail::class, [
            'userId' => $user->id,
            'payload' => [
                'title' => 'Deployment failed',
                'body' => 'The deployment failed and needs attention.',
                'level' => 'danger',
                'url' => '/admin/deployments/1',
                'context' => ['deployment_id' => 1],
            ],
        ])->handle();

        Mail::assertSentCount(1);

        $this->assertDatabaseHas('operational_alert_deliveries', [
            'user_id' => $user->id,
            'channel' => 'mail',
            'target' => 'alerts@example.com',
            'status' => 'sent',
            'title' => 'Deployment failed',
        ]);
    }
}
