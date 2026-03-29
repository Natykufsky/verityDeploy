<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'github_api_token' => 'encrypted',
            'github_oauth_client_secret' => 'encrypted',
            'github_oauth_access_token' => 'encrypted',
            'github_oauth_connected_at' => 'datetime',
            'default_ssh_port' => 'integer',
        ];
    }

    public function changes(): HasMany
    {
        return $this->hasMany(AppSettingChange::class);
    }
}
