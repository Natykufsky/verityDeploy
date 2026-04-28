<?php

namespace App\Services\Alerts;

use App\Filament\Resources\Deployments\DeploymentResource;
use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\DeliverOperationalAlertEmail;
use App\Jobs\DeliverOperationalAlertWebhooks;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use App\Services\AppSettings;

class OperationalAlertService
{
    public function notifyAll(string $title, string $body, string $level = 'warning', ?string $url = null, array $context = []): void
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'level' => $level,
            'url' => $url,
            'context' => $context,
        ];

        User::query()
            ->chunkById(100, function ($users) use ($title, $body, $level, $url, $context, $payload): void {
                foreach ($users as $user) {
                    if ($user->alertInboxEnabled() && $this->meetsMinimumLevel($level, $user->alertMinimumLevel())) {
                        $user->notify(new OperationalAlertNotification($title, $body, $level, $url, $context));
                    }

                    if (app(AppSettings::class)->alertEmailEnabled() && $user->alertEmailEnabled() && filled($user->email) && $this->meetsMinimumLevel($level, $user->alertMinimumLevel())) {
                        DeliverOperationalAlertEmail::dispatch($user->id, $payload);
                    }
                }
            });

        $settings = app(AppSettings::class);

        if ($settings->alertWebhooksEnabled() && filled($settings->alertWebhookUrls())) {
            DeliverOperationalAlertWebhooks::dispatch($payload);
        }
    }

    protected function meetsMinimumLevel(string $level, string $minimumLevel): bool
    {
        $rank = [
            'success' => 0,
            'warning' => 1,
            'danger' => 2,
        ];

        return ($rank[$level] ?? 1) >= ($rank[$minimumLevel] ?? 1);
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

    public function siteSslRefreshed(Site $site, string $message): void
    {
        $this->notifyAll(
            "SSL refreshed: {$site->name}",
            $message,
            'success',
            SiteResource::getUrl('view', ['record' => $site, 'tab' => 'SSL']),
            [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'primary_domain' => $site->primary_domain,
                'ssl_state' => $site->ssl_state,
                'force_https' => (bool) $site->force_https,
            ],
        );
    }

    public function siteHttpsRedirectSynced(Site $site, string $message): void
    {
        $this->notifyAll(
            "HTTPS redirect synced: {$site->name}",
            $message,
            'success',
            SiteResource::getUrl('view', ['record' => $site, 'tab' => 'SSL']),
            [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'primary_domain' => $site->primary_domain,
                'ssl_state' => $site->ssl_state,
                'force_https' => (bool) $site->force_https,
            ],
        );
    }

    public function siteSslActionFailed(Site $site, string $action, string $message): void
    {
        $this->notifyAll(
            "{$action} failed: {$site->name}",
            $message,
            'danger',
            SiteResource::getUrl('view', ['record' => $site, 'tab' => 'SSL']),
            [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'action' => $action,
                'primary_domain' => $site->primary_domain,
                'ssl_state' => $site->ssl_state,
                'force_https' => (bool) $site->force_https,
            ],
        );
    }
}
