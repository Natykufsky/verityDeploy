<?php

namespace App\Models;

use App\Casts\EncryptedTextOrPlain;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'default_branch',
        'default_web_root',
        'default_php_version',
        'default_deploy_source',
        'default_ssh_port',
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
            'default_ssh_port' => 'integer',
            'alert_email_enabled' => 'boolean',
            'alert_webhooks_enabled' => 'boolean',
        ];
    }

    public function changes(): HasMany
    {
        return $this->hasMany(AppSettingChange::class);
    }
}
