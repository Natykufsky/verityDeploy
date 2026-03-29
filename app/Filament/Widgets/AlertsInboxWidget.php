<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AlertsInboxPage;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class AlertsInboxWidget extends Widget
{
    protected string $view = 'filament.widgets.alerts-inbox-widget';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestAlert = $this->latestAlert();

        return [
            'inboxUrl' => AlertsInboxPage::getUrl(),
            'unreadCount' => $this->unreadCount(),
            'criticalCount' => $this->criticalCount(),
            'latestAlert' => $latestAlert,
            'latestAlertTitle' => $this->notificationTitle($latestAlert),
            'latestAlertBody' => $this->notificationBody($latestAlert),
            'latestAlertTone' => $this->notificationTone($latestAlert),
            'latestAlertWhen' => $this->notificationWhen($latestAlert),
            'hasUnread' => $this->unreadCount() > 0,
        ];
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        Notification::make()
            ->title('Inbox updated')
            ->body('All unread alerts were marked as read.')
            ->success()
            ->send();
    }

    public function openInbox(): RedirectResponse
    {
        return redirect()->to(AlertsInboxPage::getUrl());
    }

    protected function unreadCount(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    protected function criticalCount(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        return $user->notifications()
            ->whereIn('data->level', ['danger', 'warning'])
            ->count();
    }

    protected function latestAlert(): ?DatabaseNotification
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function notificationTitle(?DatabaseNotification $notification): string
    {
        return (string) data_get($notification?->data, 'title', 'No alerts yet');
    }

    protected function notificationBody(?DatabaseNotification $notification): string
    {
        return (string) data_get($notification?->data, 'body', 'You are caught up.');
    }

    protected function notificationTone(?DatabaseNotification $notification): string
    {
        $level = (string) data_get($notification?->data, 'level', 'slate');

        return match ($level) {
            'danger' => 'rose',
            'warning' => 'amber',
            'success' => 'emerald',
            default => $notification?->read_at ? 'slate' : 'amber',
        };
    }

    protected function notificationWhen(?DatabaseNotification $notification): string
    {
        return $notification?->created_at?->diffForHumans() ?? 'just now';
    }
}
