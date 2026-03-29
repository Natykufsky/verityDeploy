<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_github_oauth_token_takes_precedence_over_pat(): void
    {
        $settings = new AppSettings();

        $record = new AppSetting([
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_php_version' => '8.3',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'github_webhook_path' => '/webhooks/github',
            'github_webhook_events' => 'push',
            'github_api_token' => 'pat-token',
            'github_oauth_access_token' => 'oauth-token',
        ]);

        $reflection = new \ReflectionProperty($settings, 'cached');
        $reflection->setAccessible(true);
        $reflection->setValue($settings, $record);

        $this->assertSame('oauth-token', $settings->githubApiToken());
    }
}
