<?php

namespace Tests\Unit;

use App\Filament\Resources\Deployments\Pages\ViewDeployment;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ViewDeploymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refresh_deployment_reloads_the_latest_status_and_steps(): void
    {
        $server = Server::query()->create([
            'name' => 'Refresh Server',
            'ip_address' => '203.0.113.250',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'refresh-site',
            'deploy_path' => '/var/www/refresh-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/refresh-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'running',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/refresh-site/releases/001',
            'started_at' => now()->subMinutes(5),
        ]);

        DeploymentStep::query()->create([
            'deployment_id' => $deployment->id,
            'sequence' => 1,
            'label' => 'Prepare release',
            'command' => 'mkdir -p release',
            'status' => 'running',
            'output' => 'Preparing.',
            'started_at' => now()->subMinutes(4),
        ]);

        $page = new ViewDeployment;
        $this->setPageRecord($page, $deployment->fresh(['site.server', 'steps', 'triggeredBy']));

        $deployment->update([
            'status' => 'successful',
            'finished_at' => now(),
            'exit_code' => 0,
        ]);

        DeploymentStep::query()->create([
            'deployment_id' => $deployment->id,
            'sequence' => 2,
            'label' => 'Activate release',
            'command' => 'ln -sfn release current',
            'status' => 'successful',
            'output' => 'Activated.',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'exit_code' => 0,
        ]);

        $this->invokeProtected($page, 'refreshDeployment');

        $record = $this->getPageRecord($page);

        $this->assertSame('successful', $record->status);
        $this->assertCount(2, $record->steps);
        $this->assertSame('Activate release', $record->steps->last()->label);
    }

    public function test_jump_deployment_options_include_available_records(): void
    {
        $server = Server::query()->create([
            'name' => 'Jump Server',
            'ip_address' => '203.0.113.251',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'jump-site',
            'deploy_path' => '/var/www/jump-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/jump-site.git',
            'default_branch' => 'main',
        ]);

        $currentDeployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/jump-site/releases/001',
            'started_at' => now()->subMinutes(10),
        ]);

        $otherDeployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'failed',
            'branch' => 'release',
            'commit_hash' => 'def456',
            'release_path' => '/var/www/jump-site/releases/002',
            'started_at' => now()->subMinutes(5),
        ]);

        $otherServer = Server::query()->create([
            'name' => 'Other Jump Server',
            'ip_address' => '203.0.113.253',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $otherSite = Site::query()->create([
            'server_id' => $otherServer->id,
            'name' => 'other-jump-site',
            'deploy_path' => '/var/www/other-jump-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/other-jump-site.git',
            'default_branch' => 'main',
        ]);

        $crossSiteDeployment = Deployment::query()->create([
            'site_id' => $otherSite->id,
            'source' => 'manual',
            'status' => 'running',
            'branch' => 'feature',
            'commit_hash' => 'fedcba',
            'release_path' => '/var/www/other-jump-site/releases/003',
            'started_at' => now()->subMinutes(1),
        ]);

        $page = new ViewDeployment;
        $this->setPageRecord($page, $currentDeployment->fresh(['site.server', 'steps', 'triggeredBy']));

        $options = $this->invokeProtected($page, 'deploymentJumpOptions', [true]);
        $allOptions = $this->invokeProtected($page, 'deploymentJumpOptions', [false]);

        $this->assertArrayHasKey('jump-site', $options);
        $this->assertArrayHasKey($currentDeployment->id, $options['jump-site']);
        $this->assertArrayHasKey($otherDeployment->id, $options['jump-site']);
        $this->assertArrayNotHasKey('other-jump-site', $options);

        $this->assertArrayHasKey('jump-site', $allOptions);
        $this->assertArrayHasKey('other-jump-site', $allOptions);
        $this->assertArrayHasKey($crossSiteDeployment->id, $allOptions['other-jump-site']);
        $this->assertStringContainsString('commit: def456', $options['jump-site'][$otherDeployment->id]);
        $this->assertStringContainsString('#'.$crossSiteDeployment->id, $allOptions['other-jump-site'][$crossSiteDeployment->id]);
    }

    public function test_jump_to_deployment_redirects_to_the_selected_record(): void
    {
        $server = Server::query()->create([
            'name' => 'Jump Server',
            'ip_address' => '203.0.113.252',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'jump-site',
            'deploy_path' => '/var/www/jump-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/jump-site.git',
            'default_branch' => 'main',
        ]);

        $currentDeployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/jump-site/releases/001',
            'started_at' => now()->subMinutes(10),
        ]);

        $targetDeployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'failed',
            'branch' => 'release',
            'commit_hash' => 'def456',
            'release_path' => '/var/www/jump-site/releases/002',
            'started_at' => now()->subMinutes(5),
        ]);

        $page = new ViewDeployment;
        $this->setPageRecord($page, $currentDeployment->fresh(['site.server', 'steps', 'triggeredBy']));

        $response = $this->invokeProtected($page, 'jumpToDeployment', [$targetDeployment->id]);

        $this->assertStringContainsString((string) $targetDeployment->id, $response->getTargetUrl());
    }

    protected function setPageRecord(ViewDeployment $page, Deployment $deployment): void
    {
        $reflection = new \ReflectionProperty($page, 'record');
        $reflection->setAccessible(true);
        $reflection->setValue($page, $deployment);
    }

    protected function getPageRecord(ViewDeployment $page): Deployment
    {
        $reflection = new \ReflectionProperty($page, 'record');
        $reflection->setAccessible(true);

        return $reflection->getValue($page);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
