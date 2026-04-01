<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use App\Models\CredentialProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'app_logo_path',
        'app_favicon_path',
        'app_tagline',
        'app_description',
        'app_support_url',
        'default_branch',
        'default_web_root',
        'default_php_version',
        'default_deploy_source',
        'default_ssh_port',
        'default_ssh_credential_profile_id',
        'default_ssh_user',
        'default_ssh_key',
        'default_sudo_password',
        'default_cpanel_username',
        'default_cpanel_api_token',
        'default_cpanel_api_port',
        'default_cpanel_credential_profile_id',
        'default_dns_provider',
        'default_dns_zone_id',
        'default_dns_api_token',
        'default_dns_proxy_records',
        'default_webhook_secret',
        'default_dns_credential_profile_id',
        'default_github_credential_profile_id',
        'default_webhook_credential_profile_id',
        'github_webhook_path',
        'github_webhook_events',
        'github_api_token',
        'github_oauth_client_id',
        'github_oauth_client_secret',
        'github_oauth_access_token',
        'github_oauth_connected_at',
        'github_oauth_last_error',
        'alert_email_enabled',
        'alert_webhooks_enabled',
        'alert_webhook_urls',
        'alert_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'github_api_token' => EncryptedTextOrPlain::class,
            'github_oauth_client_secret' => EncryptedTextOrPlain::class,
            'github_oauth_access_token' => EncryptedTextOrPlain::class,
            'alert_webhook_secret' => EncryptedTextOrPlain::class,
            'github_oauth_connected_at' => 'datetime',
            'app_logo_path' => 'string',
            'app_favicon_path' => 'string',
            'app_tagline' => 'string',
            'app_description' => 'string',
            'app_support_url' => 'string',
            'default_ssh_port' => 'integer',
            'default_ssh_credential_profile_id' => 'integer',
            'default_ssh_user' => 'string',
            'default_ssh_key' => EncryptedTextOrPlain::class,
            'default_sudo_password' => EncryptedTextOrPlain::class,
            'default_cpanel_username' => 'string',
            'default_cpanel_api_token' => EncryptedTextOrPlain::class,
            'default_cpanel_api_port' => 'integer',
            'default_cpanel_credential_profile_id' => 'integer',
            'default_dns_provider' => 'string',
            'default_dns_zone_id' => 'string',
            'default_dns_api_token' => EncryptedTextOrPlain::class,
            'default_dns_proxy_records' => 'boolean',
            'default_webhook_secret' => EncryptedTextOrPlain::class,
            'default_dns_credential_profile_id' => 'integer',
            'default_github_credential_profile_id' => 'integer',
            'default_webhook_credential_profile_id' => 'integer',
            'alert_email_enabled' => 'boolean',
            'alert_webhooks_enabled' => 'boolean',
        ];
    }

    public function changes(): HasMany
    {
        return $this->hasMany(AppSettingChange::class);
    }

    public function defaultSshCredentialProfile(): ?CredentialProfile
    {
        return filled($this->default_ssh_credential_profile_id)
            ? CredentialProfile::query()->find($this->default_ssh_credential_profile_id)
            : null;
    }

    public function defaultCpanelCredentialProfile(): ?CredentialProfile
    {
        return filled($this->default_cpanel_credential_profile_id)
            ? CredentialProfile::query()->find($this->default_cpanel_credential_profile_id)
            : null;
    }

    public function defaultDnsCredentialProfile(): ?CredentialProfile
    {
        return filled($this->default_dns_credential_profile_id)
            ? CredentialProfile::query()->find($this->default_dns_credential_profile_id)
            : null;
    }

    public function defaultGithubCredentialProfile(): ?CredentialProfile
    {
        return filled($this->default_github_credential_profile_id)
            ? CredentialProfile::query()->find($this->default_github_credential_profile_id)
            : null;
    }

    public function defaultWebhookCredentialProfile(): ?CredentialProfile
    {
        return filled($this->default_webhook_credential_profile_id)
            ? CredentialProfile::query()->find($this->default_webhook_credential_profile_id)
            : null;
    }
}
