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
