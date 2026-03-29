<?php

namespace App\Services\Alerts;

use App\Filament\Resources\Deployments\DeploymentResource;
use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;

class OperationalAlertService
{
    public function notifyAll(string $title, string $body, string $level = 'warning', ?string $url = null, array $context = []): void
    {
        User::query()
            ->chunkById(100, function ($users) use ($title, $body, $level, $url, $context): void {
                foreach ($users as $user) {
                    $user->notify(new OperationalAlertNotification($title, $body, $level, $url, $context));
                }
            });
    }

    public function deployFailed(Deployment $deployment, string $message, ?string $hint = null): void
    {
        $site = $deployment->site->fresh();

        $this->notifyAll(
            "Deployment failed: {$site->name}",
            $hint ? "{$message} Recovery: {$hint}" : $message,
            'danger',
            DeploymentResource::getUrl('view', ['record' => $deployment]),
            [
                'deployment_id' => $deployment->id,
                'site_id' => $site->id,
                'site_name' => $site->name,
                'release_path' => $deployment->release_path,
            ],
        );
    }

    public function serverUnhealthy(Server $server, string $message): void
    {
        $this->notifyAll(
            "Server unhealthy: {$server->name}",
            $message,
            'danger',
            ServerResource::getUrl('view', ['record' => $server]),
            [
                'server_id' => $server->id,
                'server_name' => $server->name,
                'status' => $server->status,
            ],
        );
    }

    public function webhookDrift(Site $site, string $message): void
    {
        $this->notifyAll(
            "Webhook drift: {$site->name}",
            $message,
            'warning',
            SiteResource::getUrl('webhooks', ['record' => $site]),
            [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'github_webhook_id' => $site->github_webhook_id,
            ],
        );
    }
}
