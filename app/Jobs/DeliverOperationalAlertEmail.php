<?php

namespace App\Jobs;

use App\Mail\OperationalAlertMail;
use App\Models\OperationalAlertDelivery;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverOperationalAlertEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || blank($user->email)) {
            OperationalAlertDelivery::query()->create([
                'user_id' => $this->userId,
                'channel' => 'mail',
                'target' => $user?->email,
                'title' => (string) data_get($this->payload, 'title', 'verityDeploy operational alert'),
                'level' => (string) data_get($this->payload, 'level', 'warning'),
                'status' => 'skipped',
                'error_message' => 'No email address available for delivery.',
                'payload' => $this->payload,
            ]);

            return;
        }

        $delivery = OperationalAlertDelivery::query()->create([
            'user_id' => $user->id,
            'channel' => 'mail',
            'target' => $user->email,
            'title' => (string) data_get($this->payload, 'title', 'verityDeploy operational alert'),
            'level' => (string) data_get($this->payload, 'level', 'warning'),
            'status' => 'queued',
            'payload' => $this->payload,
        ]);

        try {
            Mail::to($user->email)->send(new OperationalAlertMail($this->payload));

            $delivery->update([
                'status' => 'sent',
                'delivered_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $throwable) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ]);
        }
    }
}
