<?php

namespace App\Filament\Pages;

use App\Models\OperationalAlertDelivery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class AlertsInboxPage extends Page
{
    protected static UnitEnum|string|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Alerts Inbox';

    protected static ?string $title = 'Alerts Inbox';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $slug = 'alerts-inbox';

    protected ?string $pollingInterval = '15s';

    public string $filter = 'unread';

    public ?string $activeNotificationId = null;

    public function infolist(Schema $schema): Schema
    {
        return $this->content($schema);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Operational alerts')
                    ->schema([
                        View::make('filament.pages.alerts-inbox')
                            ->viewData(fn (): array => $this->inboxViewData()),
                    ]),
                Section::make('Delivery status')
                    ->schema([
                        View::make('filament.pages.alert-delivery-log')
                            ->viewData(fn (): array => [
                                'deliveries' => $this->recentDeliveries(),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label('Mark all read')
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->visible(fn (): bool => $this->unreadCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('Mark all unread alerts as read?')
                ->modalDescription('This clears the unread badge but keeps the alerts in your inbox for later reference.')
                ->modalSubmitActionLabel('Mark all read')
                ->action(fn () => $this->markAllAsRead()),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $count = $user->unreadNotifications()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<string, mixed>
     */
    protected function inboxViewData(): array
    {
        return [
            'filter' => $this->filter,
            'filters' => [
                'unread' => 'Unread',
                'all' => 'All',
                'critical' => 'Critical',
                'read' => 'Read',
            ],
            'stats' => [
                'total' => $this->totalCount(),
                'unread' => $this->unreadCount(),
                'critical' => $this->criticalCount(),
                'recent' => $this->recentCount(),
            ],
            'notifications' => $this->inboxNotifications(),
            'notificationIds' => $this->inboxNotifications()->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function inboxNotifications()
    {
        return $this->baseQuery()
            ->latest()
            ->limit(50)
            ->get();
    }

    public function totalCount(): int
    {
        return $this->baseQueryWithoutFilter()->count();
    }

    public function unreadCount(): int
    {
        return $this->baseQueryWithoutFilter()
            ->whereNull('read_at')
            ->count();
    }

    public function criticalCount(): int
    {
        return $this->baseQueryWithoutFilter()
            ->whereIn('data->level', ['danger', 'warning'])
            ->count();
    }

    public function recentCount(): int
    {
        return $this->baseQueryWithoutFilter()
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public function setFilter(string $filter): void
    {
        $allowed = array_keys([
            'unread' => true,
            'all' => true,
            'critical' => true,
            'read' => true,
        ]);

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->filter = $filter;
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

    public function markAsRead(string $notificationId): void
    {
        $this->updateNotificationReadState($notificationId, now());
    }

    public function markAsUnread(string $notificationId): void
    {
        $this->updateNotificationReadState($notificationId, null);
    }

    public function dismiss(string $notificationId): void
    {
        $notification = $this->findNotification($notificationId);

        if (! $notification) {
            return;
        }

        $notification->delete();

        if ($this->activeNotificationId === $notificationId) {
            $this->activeNotificationId = null;
        }

        Notification::make()
            ->title('Alert dismissed')
            ->body('The alert was removed from the inbox.')
            ->success()
            ->send();
    }

    public function openNotification(string $notificationId): void
    {
        $notification = $this->findNotification($notificationId);

        if (! $notification) {
            return;
        }

        $notification->markAsRead();
        $this->activeNotificationId = $notification->id;

        Notification::make()
            ->title('Alert opened')
            ->body('The alert is now open in the modal.')
            ->success()
            ->send();
    }

    public function closeNotification(): void
    {
        $this->activeNotificationId = null;
    }

    public function nextNotification(): void
    {
        $notification = $this->activeNotification();

        if (! $notification) {
            return;
        }

        $notifications = $this->inboxNotifications()->values();
        $currentIndex = $notifications->search(fn (DatabaseNotification $item): bool => $item->is($notification));

        if ($currentIndex === false) {
            return;
        }

        $next = $notifications->get($currentIndex + 1);

        if (! $next instanceof DatabaseNotification) {
            return;
        }

        $this->activeNotificationId = $next->id;
        $next->markAsRead();
    }

    public function previousNotification(): void
    {
        $notification = $this->activeNotification();

        if (! $notification) {
            return;
        }

        $notifications = $this->inboxNotifications()->values();
        $currentIndex = $notifications->search(fn (DatabaseNotification $item): bool => $item->is($notification));

        if ($currentIndex === false || $currentIndex === 0) {
            return;
        }

        $previous = $notifications->get($currentIndex - 1);

        if (! $previous instanceof DatabaseNotification) {
            return;
        }

        $this->activeNotificationId = $previous->id;
        $previous->markAsRead();
    }

    public function activeNotification(): ?DatabaseNotification
    {
        if (! filled($this->activeNotificationId)) {
            return null;
        }

        return $this->findNotification($this->activeNotificationId);
    }

    public function notificationTitle(DatabaseNotification $notification): string
    {
        return (string) data_get($notification->data, 'title', 'Alert');
    }

    public function notificationBody(DatabaseNotification $notification): string
    {
        return (string) data_get($notification->data, 'body', 'No details available.');
    }

    public function notificationTone(DatabaseNotification $notification): string
    {
        $level = (string) data_get($notification->data, 'level', 'warning');

        return match ($level) {
            'danger' => 'rose',
            'warning' => 'amber',
            'success' => 'emerald',
            default => $notification->read_at ? 'slate' : 'amber',
        };
    }

    public function notificationUrl(DatabaseNotification $notification): ?string
    {
        $url = data_get($notification->data, 'url');

        return filled($url) ? (string) $url : null;
    }

    public function notificationContext(DatabaseNotification $notification): array
    {
        $context = data_get($notification->data, 'context', []);

        return is_array($context) ? $context : [];
    }

    public function notificationWhen(DatabaseNotification $notification): string
    {
        return $notification->created_at?->diffForHumans() ?? 'just now';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentDeliveries(): array
    {
        return OperationalAlertDelivery::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (OperationalAlertDelivery $delivery): array {
                return [
                    'title' => $delivery->title,
                    'channel' => $delivery->channel,
                    'target' => $delivery->target,
                    'status' => $delivery->status,
                    'level' => $delivery->level,
                    'error_message' => $delivery->error_message,
                    'delivered_at' => $delivery->delivered_at?->diffForHumans() ?? $delivery->created_at?->diffForHumans() ?? 'just now',
                ];
            })
            ->all();
    }

    protected function updateNotificationReadState(string $notificationId, ?Carbon $readAt): void
    {
        $notification = $this->findNotification($notificationId);

        if (! $notification) {
            return;
        }

        $notification->update([
            'read_at' => $readAt,
        ]);

        if ($this->activeNotificationId === $notificationId) {
            $this->activeNotificationId = $readAt ? $notificationId : $notificationId;
        }

        Notification::make()
            ->title($readAt ? 'Alert marked as read' : 'Alert marked as unread')
            ->success()
            ->send();
    }

    protected function findNotification(string $notificationId): ?DatabaseNotification
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return $user->notifications()->whereKey($notificationId)->first();
    }

    protected function baseQueryWithoutFilter()
    {
        $user = auth()->user();

        if (! $user) {
            return DatabaseNotification::query()->whereRaw('1 = 0');
        }

        return $user->notifications();
    }

    protected function baseQuery()
    {
        $query = $this->baseQueryWithoutFilter();

        return match ($this->filter) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            'critical' => $query->whereIn('data->level', ['danger', 'warning']),
            default => $query,
        };
    }
}
