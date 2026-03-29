<?php

namespace App\Filament\Pages;

use App\Notifications\OperationalAlertNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
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

    public function infolist(Schema $schema): Schema
    {
        return $this->content($schema);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('How this inbox works')
                    ->schema([
                        View::make('filament.pages.alerts-inbox-guide'),
                    ]),
                Section::make('Operational alerts')
                    ->schema([
                        View::make('filament.pages.alerts-inbox')
                            ->viewData(fn (): array => $this->inboxViewData()),
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
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, DatabaseNotification>
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

        Notification::make()
            ->title('Alert dismissed')
            ->body('The alert was removed from the inbox.')
            ->success()
            ->send();
    }

    public function openNotification(string $notificationId): ?RedirectResponse
    {
        $notification = $this->findNotification($notificationId);

        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        $url = data_get($notification->data, 'url');

        if (filled($url)) {
            return redirect()->to($url);
        }

        Notification::make()
            ->title('Alert opened')
            ->body('The alert has been marked as read.')
            ->success()
            ->send();

        return null;
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

    protected function updateNotificationReadState(string $notificationId, ?\Illuminate\Support\Carbon $readAt): void
    {
        $notification = $this->findNotification($notificationId);

        if (! $notification) {
            return;
        }

        $notification->update([
            'read_at' => $readAt,
        ]);

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
