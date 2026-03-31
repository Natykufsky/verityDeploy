<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSettings
{
    protected ?AppSetting $cached = null;

    public function record(): AppSetting
    {
        if ($this->cached instanceof AppSetting) {
            return $this->cached;
        }

        try {
            if (! Schema::hasTable('app_settings')) {
                return $this->cached = $this->fallbackRecord();
            }

            return $this->cached = AppSetting::query()->firstOrCreate([
                'id' => 1,
            ], [
                'app_name' => config('app.name', 'verityDeploy'),
                'default_branch' => 'main',
                'default_web_root' => 'public',
                'default_php_version' => '8.3',
                'default_deploy_source' => 'git',
                'default_ssh_port' => 22,
                'github_webhook_path' => '/webhooks/github',
                'github_webhook_events' => 'push',
                'github_oauth_client_id' => null,
                'github_oauth_client_secret' => null,
                'github_oauth_access_token' => null,
                'alert_email_enabled' => false,
                'alert_webhooks_enabled' => false,
                'alert_webhook_urls' => null,
                'alert_webhook_secret' => null,
            ]);
        } catch (Throwable) {
            return $this->cached = $this->fallbackRecord();
        }
    }

    protected function fallbackRecord(): AppSetting
    {
        return new AppSetting([
            'app_name' => config('app.name', 'verityDeploy'),
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_php_version' => '8.3',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'github_webhook_path' => '/webhooks/github',
            'github_webhook_events' => 'push',
            'github_oauth_client_id' => null,
            'github_oauth_client_secret' => null,
            'github_oauth_access_token' => null,
            'alert_email_enabled' => false,
            'alert_webhooks_enabled' => false,
            'alert_webhook_urls' => null,
            'alert_webhook_secret' => null,
        ]);
    }

    public function appName(): string
    {
        return (string) $this->record()->app_name ?: config('app.name', 'verityDeploy');
    }

    public function defaultBranch(): string
    {
        return (string) ($this->record()->default_branch ?: 'main');
    }

    public function defaultWebRoot(): string
    {
        return (string) ($this->record()->default_web_root ?: 'public');
    }

    public function defaultPhpVersion(): ?string
    {
        $version = $this->record()->default_php_version;

        return filled($version) ? (string) $version : null;
    }

    public function defaultDeploySource(): string
    {
        return (string) ($this->record()->default_deploy_source ?: 'git');
    }

    public function defaultSshPort(): int
    {
        return (int) ($this->record()->default_ssh_port ?: 22);
    }

    public function githubApiToken(): ?string
    {
        $oauthToken = $this->record()->github_oauth_access_token;

        if (filled($oauthToken)) {
            return (string) $oauthToken;
        }

        $token = $this->record()->github_api_token;

        return filled($token) ? (string) $token : (env('GITHUB_API_TOKEN') ?: null);
    }

    public function githubOAuthClientId(): ?string
    {
        $value = $this->record()->github_oauth_client_id;

        return filled($value) ? (string) $value : null;
    }

    public function githubOAuthClientSecret(): ?string
    {
        $value = $this->record()->github_oauth_client_secret;

        return filled($value) ? (string) $value : null;
    }

    public function githubOAuthConnectedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->record()->github_oauth_connected_at;
    }

    public function githubWebhookPath(): string
    {
        return (string) ($this->record()->github_webhook_path ?: '/webhooks/github');
    }

    /**
     * @return array<int, string>
     */
    public function githubWebhookEvents(): array
    {
        $events = (string) ($this->record()->github_webhook_events ?: 'push');

        return collect(explode(',', $events))
            ->map(fn (string $event): string => trim($event))
            ->filter()
            ->values()
            ->all();
    }

    public function alertEmailEnabled(): bool
    {
        return (bool) $this->record()->alert_email_enabled;
    }

    public function alertWebhooksEnabled(): bool
    {
        return (bool) $this->record()->alert_webhooks_enabled;
    }

    /**
     * @return array<int, string>
     */
    public function alertWebhookUrls(): array
    {
        $urls = (string) ($this->record()->alert_webhook_urls ?: '');

        return collect(preg_split('/\r\n|\r|\n/', $urls) ?: [])
            ->map(fn (string $url): string => trim($url))
            ->filter()
            ->values()
            ->all();
    }

    public function alertWebhookSecret(): ?string
    {
        $secret = $this->record()->alert_webhook_secret;

        return filled($secret) ? (string) $secret : null;
    }
}
