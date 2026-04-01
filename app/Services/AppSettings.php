<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CredentialProfile;
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
                'app_logo_path' => null,
                'app_favicon_path' => null,
                'app_tagline' => null,
                'app_description' => null,
                'app_support_url' => null,
                'default_branch' => 'main',
                'default_web_root' => 'public',
                'default_php_version' => '8.3',
                'default_deploy_source' => 'git',
                'default_ssh_port' => 22,
                'default_ssh_credential_profile_id' => null,
                'default_ssh_user' => null,
                'default_ssh_key' => null,
                'default_sudo_password' => null,
                'default_cpanel_username' => null,
                'default_cpanel_api_token' => null,
                'default_cpanel_api_port' => 2083,
                'default_cpanel_credential_profile_id' => null,
                'default_dns_provider' => 'manual',
                'default_dns_zone_id' => null,
                'default_dns_api_token' => null,
                'default_dns_proxy_records' => true,
                'default_webhook_secret' => null,
                'default_dns_credential_profile_id' => null,
                'default_github_credential_profile_id' => null,
                'default_webhook_credential_profile_id' => null,
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
            'app_logo_path' => null,
            'app_favicon_path' => null,
            'app_tagline' => null,
            'app_description' => null,
            'app_support_url' => null,
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_php_version' => '8.3',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'default_ssh_credential_profile_id' => null,
            'default_ssh_user' => null,
            'default_ssh_key' => null,
            'default_sudo_password' => null,
            'default_cpanel_username' => null,
            'default_cpanel_api_token' => null,
            'default_cpanel_api_port' => 2083,
            'default_cpanel_credential_profile_id' => null,
            'default_dns_provider' => 'manual',
            'default_dns_zone_id' => null,
            'default_dns_api_token' => null,
            'default_dns_proxy_records' => true,
            'default_webhook_secret' => null,
            'default_dns_credential_profile_id' => null,
            'default_github_credential_profile_id' => null,
            'default_webhook_credential_profile_id' => null,
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

    public function appLogoPath(): ?string
    {
        $value = $this->record()->app_logo_path;

        return filled($value) ? (string) $value : null;
    }

    public function appFaviconPath(): ?string
    {
        $value = $this->record()->app_favicon_path;

        return filled($value) ? (string) $value : null;
    }

    public function appTagline(): ?string
    {
        $value = $this->record()->app_tagline;

        return filled($value) ? (string) $value : null;
    }

    public function appDescription(): ?string
    {
        $value = $this->record()->app_description;

        return filled($value) ? (string) $value : null;
    }

    public function appSupportUrl(): ?string
    {
        $value = $this->record()->app_support_url;

        return filled($value) ? (string) $value : null;
    }

    public function brandLogoUrl(): ?string
    {
        $path = $this->appLogoPath();

        return filled($path) ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }

    public function faviconUrl(): ?string
    {
        $path = $this->appFaviconPath();

        return filled($path) ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
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

    public function defaultSshCredentialProfileId(): ?int
    {
        $value = $this->record()->default_ssh_credential_profile_id;

        return filled($value) ? (int) $value : null;
    }

    public function defaultSshCredentialProfile(): ?CredentialProfile
    {
        return filled($this->defaultSshCredentialProfileId())
            ? CredentialProfile::query()->find($this->defaultSshCredentialProfileId())
            : null;
    }

    public function defaultSshUser(): ?string
    {
        $value = $this->record()->default_ssh_user;

        return filled($value) ? (string) $value : null;
    }

    public function defaultSshKey(): ?string
    {
        $value = $this->record()->default_ssh_key;

        return filled($value) ? (string) $value : null;
    }

    public function defaultSudoPassword(): ?string
    {
        $value = $this->record()->default_sudo_password;

        return filled($value) ? (string) $value : null;
    }

    public function defaultCpanelUsername(): ?string
    {
        $value = $this->record()->default_cpanel_username;

        return filled($value) ? (string) $value : null;
    }

    public function defaultCpanelApiToken(): ?string
    {
        $value = $this->record()->default_cpanel_api_token;

        return filled($value) ? (string) $value : null;
    }

    public function defaultCpanelApiPort(): int
    {
        return (int) ($this->record()->default_cpanel_api_port ?: 2083);
    }

    public function defaultCpanelCredentialProfileId(): ?int
    {
        $value = $this->record()->default_cpanel_credential_profile_id;

        return filled($value) ? (int) $value : null;
    }

    public function defaultCpanelCredentialProfile(): ?CredentialProfile
    {
        return filled($this->defaultCpanelCredentialProfileId())
            ? CredentialProfile::query()->find($this->defaultCpanelCredentialProfileId())
            : null;
    }

    public function defaultDnsProvider(): string
    {
        return (string) ($this->record()->default_dns_provider ?: 'manual');
    }

    public function defaultDnsZoneId(): ?string
    {
        $value = $this->record()->default_dns_zone_id;

        return filled($value) ? (string) $value : null;
    }

    public function defaultDnsApiToken(): ?string
    {
        $value = $this->record()->default_dns_api_token;

        return filled($value) ? (string) $value : null;
    }

    public function defaultDnsProxyRecords(): bool
    {
        return (bool) $this->record()->default_dns_proxy_records;
    }

    public function defaultDnsCredentialProfileId(): ?int
    {
        $value = $this->record()->default_dns_credential_profile_id;

        return filled($value) ? (int) $value : null;
    }

    public function defaultDnsCredentialProfile(): ?CredentialProfile
    {
        return filled($this->defaultDnsCredentialProfileId())
            ? CredentialProfile::query()->find($this->defaultDnsCredentialProfileId())
            : null;
    }

    public function defaultWebhookSecret(): ?string
    {
        $value = $this->record()->default_webhook_secret;

        return filled($value) ? (string) $value : null;
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

    public function defaultGithubCredentialProfileId(): ?int
    {
        $value = $this->record()->default_github_credential_profile_id;

        return filled($value) ? (int) $value : null;
    }

    public function defaultGithubCredentialProfile(): ?CredentialProfile
    {
        return filled($this->defaultGithubCredentialProfileId())
            ? CredentialProfile::query()->find($this->defaultGithubCredentialProfileId())
            : null;
    }

    public function defaultWebhookCredentialProfileId(): ?int
    {
        $value = $this->record()->default_webhook_credential_profile_id;

        return filled($value) ? (int) $value : null;
    }

    public function defaultWebhookCredentialProfile(): ?CredentialProfile
    {
        return filled($this->defaultWebhookCredentialProfileId())
            ? CredentialProfile::query()->find($this->defaultWebhookCredentialProfileId())
            : null;
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
