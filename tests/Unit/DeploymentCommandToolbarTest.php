<?php

namespace Tests\Unit;

use App\Livewire\DeploymentCommandToolbar;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeploymentCommandToolbarTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_the_top_copy_commands(): void
    {
        $server = Server::query()->create([
            'name' => 'Toolbar Server',
            'ip_address' => '203.0.113.240',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'toolbar-site',
            'deploy_path' => '/var/www/toolbar-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/toolbar-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/toolbar-site/releases/001',
            'started_at' => now(),
            'finished_at' => now(),
            'exit_code' => 0,
        ]);

        $widget = new DeploymentCommandToolbar();
        $widget->record = $deployment;

        $view = $widget->render()->getData();

        $this->assertCount(4, $view['snippets']);
        $this->assertSame('Check the deployed branch', $view['snippets'][0]['title']);
        $this->assertStringContainsString('git status --short --branch', $view['snippets'][0]['command']);
    }
}
