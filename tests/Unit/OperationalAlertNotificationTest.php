<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationalAlertNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_uses_the_database_channel_for_inbox_delivery(): void
    {
        $user = User::query()->create([
            'name' => 'Alert User',
            'email' => 'alerts@example.com',
            'password' => bcrypt('password'),
        ]);

        $notification = new OperationalAlertNotification(
            'Deployment failed',
            'The deployment failed and needs attention.',
            'danger',
            '/admin/deployments/1',
            ['deployment_id' => 1],
        );

        $channels = $notification->via($user);

        $this->assertSame(['database'], $channels);
    }
}
