<?php

namespace Tests\Unit;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeploymentCommandGuideTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_builds_source_aware_command_snippets(): void
    {
        $server = Server::query()->create([
            'name' => 'Guide Server',
            'ip_address' => '203.0.113.230',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'guide-site',
            'deploy_path' => '/var/www/guide-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/guide-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/guide-site/releases/001',
            'started_at' => now(),
            'finished_at' => now(),
            'exit_code' => 0,
        ]);

        $intro = $deployment->command_guide_intro;
        $snippets = $deployment->command_guide_snippets;

        $this->assertStringContainsString('snippets', $intro);
        $this->assertNotEmpty($snippets);
        $this->assertSame('Check the deployed branch', $snippets[0]['title']);
        $this->assertStringContainsString('git status --short --branch', $snippets[0]['command']);
        $this->assertStringContainsString('/var/www/guide-site/releases/001', $snippets[0]['command']);
    }
}
