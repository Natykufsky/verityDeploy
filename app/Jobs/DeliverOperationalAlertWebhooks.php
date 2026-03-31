<?php

namespace App\Jobs;

use App\Models\OperationalAlertDelivery;
use App\Services\AppSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverOperationalAlertWebhooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 20;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function handle(AppSettings $settings): void
    {
        if (! $settings->alertWebhooksEnabled()) {
            return;
        }

        $urls = $settings->alertWebhookUrls();

        if ($urls === []) {
            return;
        }

        $body = array_merge([
            'event' => 'operational.alert',
            'occurred_at' => now()->toIso8601String(),
        ], $this->payload);

        $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $secret = $settings->alertWebhookSecret();

        foreach ($urls as $url) {
            $delivery = OperationalAlertDelivery::query()->create([
                'user_id' => null,
                'channel' => 'webhook',
                'target' => $url,
                'title' => (string) data_get($body, 'title', 'verityDeploy operational alert'),
                'level' => (string) data_get($body, 'level', 'warning'),
                'status' => 'queued',
                'payload' => $body,
            ]);

            try {
                $headers = [
                    'X-VerityDeploy-Event' => 'operational.alert',
                    'X-VerityDeploy-Level' => (string) data_get($body, 'level', 'warning'),
                ];

                if (filled($secret)) {
                    $headers['X-VerityDeploy-Signature'] = hash_hmac('sha256', $encodedBody, $secret);
                }

                $response = Http::asJson()
                    ->acceptJson()
                    ->timeout(5)
                    ->retry(2, 500)
                    ->withHeaders($headers)
                    ->post($url, $body);

                $response->throw();

                $delivery->update([
                    'status' => 'sent',
                    'response_code' => $response->status(),
                    'delivered_at' => now(),
                    'error_message' => null,
                ]);
            } catch (Throwable $throwable) {
                $delivery->update([
                    'status' => 'failed',
                    'error_message' => $throwable->getMessage(),
                ]);

                Log::warning('Operational alert webhook delivery failed.', [
                    'url' => $url,
                    'title' => data_get($body, 'title'),
                    'message' => $throwable->getMessage(),
                ]);
            }
        }
    }
}
