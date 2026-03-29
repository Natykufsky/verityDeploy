<?php

namespace Tests\Unit;

use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Widgets\ReleaseCleanupOverviewCard;
use App\Models\ReleaseCleanupRun;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReleaseCleanupOverviewCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_cleanup_counts_and_latest_run_copy(): void
    {
        $server = Server::query()->create([
            'name' => 'Cleanup Server',
            'ip_address' => '203.0.113.180',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'cleanup-site',
            'deploy_path' => '/var/www/cleanup-site',
            'deploy_source' => 'git',
        ]);

        ReleaseCleanupRun::query()->create([
            'site_id' => $site->id,
            'status' => 'successful',
            'keep_count' => 5,
            'output' => 'Removed two old release directories.',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
        ]);

        $widget = new ReleaseCleanupOverviewCard();
        $viewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame(1, $viewData['totalRuns']);
        $this->assertSame(1, $viewData['successfulRuns']);
        $this->assertSame(0, $viewData['failedRuns']);
        $this->assertSame(0, $viewData['runningRuns']);
        $this->assertSame('cleanup-site', $viewData['latestRunLabel']);
        $this->assertSame(SiteResource::getUrl('view', ['record' => $site]), $viewData['latestRunUrl']);
        $this->assertStringContainsString('keep 5', $viewData['latestRunSummary']);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
