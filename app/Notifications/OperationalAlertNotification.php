<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationalAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $level = 'warning',
        public ?string $url = null,
        public array $context = [],
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'level' => $this->level,
            'url' => $this->url,
            'context' => $this->context,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
