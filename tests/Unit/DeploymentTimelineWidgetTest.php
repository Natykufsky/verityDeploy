<?php

namespace Tests\Unit;

use App\Filament\Resources\Deployments\DeploymentResource;
use App\Filament\Widgets\DeploymentTimelineWidget;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeploymentTimelineWidgetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_the_latest_deployment_and_step_chips(): void
    {
        $baselineSuccessful = Deployment::query()->where('status', 'successful')->count();
        $baselineFailed = Deployment::query()->where('status', 'failed')->count();
        $baselineRunning = Deployment::query()->where('status', 'running')->count();

        $server = Server::query()->create([
            'name' => 'Timeline Server',
            'ip_address' => '203.0.113.210',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'timeline-site',
            'deploy_path' => '/var/www/timeline-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/timeline-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = Deployment::query()->create([
            'site_id' => $site->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'abc123',
            'release_path' => '/var/www/timeline-site/releases/001',
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(18),
            'exit_code' => 0,
        ]);

        DeploymentStep::query()->create([
            'deployment_id' => $deployment->id,
            'sequence' => 1,
            'label' => 'Fetch latest code',
            'command' => 'git fetch',
            'status' => 'successful',
            'output' => 'Fetched.',
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(19),
            'exit_code' => 0,
        ]);

        DeploymentStep::query()->create([
            'deployment_id' => $deployment->id,
            'sequence' => 2,
            'label' => 'Install dependencies',
            'command' => 'composer install',
            'status' => 'successful',
            'output' => 'Installed.',
            'started_at' => now()->subMinutes(19),
            'finished_at' => now()->subMinutes(18),
            'exit_code' => 0,
        ]);

        $widget = new DeploymentTimelineWidget;
        $viewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame('timeline-site', $viewData['latestDeploymentLabel']);
        $this->assertSame(DeploymentResource::getUrl('view', ['record' => $deployment]), $viewData['latestDeploymentUrl']);
        $this->assertSame($baselineSuccessful + 1, $viewData['successfulCount']);
        $this->assertSame($baselineFailed, $viewData['failedCount']);
        $this->assertSame($baselineRunning, $viewData['runningCount']);
        $this->assertCount(2, $viewData['stepChips']);
        $this->assertSame('Install dependencies', $viewData['stepChips'][0]['label']);
        $this->assertSame('Successful', $viewData['stepChips'][0]['status']);

        $widget->openStepDetail($deployment->id, 2);
        $selectedViewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame('Install dependencies', $selectedViewData['selectedStepDetail']['step_label']);
        $this->assertSame('composer install', $selectedViewData['selectedStepDetail']['command']);
        $this->assertSame('Installed.', $selectedViewData['selectedStepDetail']['output']);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
