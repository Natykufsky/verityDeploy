<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Alerts\OperationalAlertService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
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
}
