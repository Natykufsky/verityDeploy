<?php

namespace Tests\Unit;

use App\Filament\Pages\AlertsInboxPage;
use App\Filament\Widgets\AlertsInboxWidget;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class AlertsInboxWidgetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_unread_alert_counts_and_latest_alert_copy(): void
    {
        $user = User::query()->create([
            'name' => 'Widget User',
            'email' => 'widget@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $user->notify(new OperationalAlertNotification(
            'Deployment failed',
            'The deployment needs a human eye.',
            'danger',
            '/admin/deployments/1',
            ['deployment_id' => 1],
        ));

        $user->notify(new OperationalAlertNotification(
            'Webhook drift',
            'The webhook should be re-provisioned.',
            'warning',
            '/admin/sites/1/webhooks',
            ['site_id' => 1],
        ));

        $widget = new AlertsInboxWidget();
        $viewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame(2, $viewData['unreadCount']);
        $this->assertSame(2, $viewData['criticalCount']);
        $this->assertContains($viewData['latestAlertTitle'], [
            'Deployment failed',
            'Server healthy',
            'Webhook drift',
        ]);
        $this->assertSame(AlertsInboxPage::getUrl(), $viewData['inboxUrl']);
    }

    public function test_it_can_mark_all_read_and_open_the_inbox(): void
    {
        $user = User::query()->create([
            'name' => 'Widget User',
            'email' => 'widget2@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $user->notify(new OperationalAlertNotification(
            'Server unhealthy',
            'The latest health check failed.',
            'danger',
            '/admin/servers/1',
            ['server_id' => 1],
        ));

        $widget = new AlertsInboxWidget();
        $redirect = $widget->openInbox();

        $this->assertInstanceOf(RedirectResponse::class, $redirect);
        $this->assertSame(AlertsInboxPage::getUrl(), $redirect->getTargetUrl());

        $widget->markAllAsRead();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
