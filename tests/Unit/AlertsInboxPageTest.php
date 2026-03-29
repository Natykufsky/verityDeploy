<?php

namespace Tests\Unit;

use App\Filament\Pages\AlertsInboxPage;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class AlertsInboxPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_lists_and_manages_alert_notifications(): void
    {
        $user = User::query()->create([
            'name' => 'Inbox User',
            'email' => 'inbox@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $user->notify(new OperationalAlertNotification(
            'Deployment failed',
            'The deployment failed and needs attention.',
            'danger',
            '/admin/deployments/1',
            ['deployment_id' => 1],
        ));

        $user->notify(new OperationalAlertNotification(
            'Server healthy',
            'The latest health check passed.',
            'success',
            '/admin/servers/1',
            ['server_id' => 1],
        ));

        $user->notify(new OperationalAlertNotification(
            'Webhook drift',
            'The GitHub webhook needs to be re-provisioned.',
            'warning',
            '/admin/sites/1/webhooks',
            ['site_id' => 1],
        ));

        $readNotification = $user->notifications()->where('data->title', 'Server healthy')->firstOrFail();
        $readNotification->markAsRead();

        $page = new AlertsInboxPage();
        $page->filter = 'unread';

        $this->assertSame(3, $page->totalCount());
        $this->assertSame(2, $page->unreadCount());
        $this->assertSame(2, $page->criticalCount());
        $this->assertCount(2, $page->inboxNotifications());
        $this->assertSame('2', AlertsInboxPage::getNavigationBadge());

        $firstNotification = $user->notifications()->where('data->title', 'Deployment failed')->firstOrFail();
        $redirect = $page->openNotification($firstNotification->id);

        $this->assertInstanceOf(RedirectResponse::class, $redirect);
        $this->assertNotNull($firstNotification->fresh()->read_at);

        $page->markAllAsRead();

        $this->assertSame(0, $page->unreadCount());
        $this->assertNull(AlertsInboxPage::getNavigationBadge());
    }
}
