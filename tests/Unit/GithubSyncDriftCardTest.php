<?php

namespace Tests\Unit;

use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Widgets\GithubSyncDriftCard;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GithubSyncDriftCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_drift_counts_and_latest_drift_copy(): void
    {
        $baselineProvisioned = Site::query()->where('github_webhook_status', 'provisioned')->count();
        $baselineDrift = Site::query()->whereIn('github_webhook_status', ['needs-sync', 'failed'])->count();
        $baselineFailed = Site::query()->where('github_webhook_status', 'failed')->count();

        $server = Server::query()->create([
            'name' => 'Drift Server',
            'ip_address' => '203.0.113.220',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'drift-site',
            'deploy_path' => '/home/demo/drift-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/drift-site.git',
            'github_webhook_status' => 'needs-sync',
            'github_webhook_last_error' => 'Missing webhook on GitHub.',
            'github_webhook_synced_at' => now()->subHour(),
        ]);

        Site::query()->create([
            'server_id' => $server->id,
            'name' => 'healthy-site',
            'deploy_path' => '/home/demo/healthy-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/healthy-site.git',
            'github_webhook_status' => 'provisioned',
            'github_webhook_synced_at' => now()->subHour(),
        ]);

        $failedSite = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'failed-site',
            'deploy_path' => '/home/demo/failed-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/failed-site.git',
            'github_webhook_status' => 'failed',
            'github_webhook_last_error' => 'GitHub API request failed.',
            'github_webhook_synced_at' => now()->subMinutes(20),
        ]);

        $widget = new GithubSyncDriftCard();
        $viewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame($baselineProvisioned + 1, $viewData['provisionedCount']);
        $this->assertSame($baselineDrift + 2, $viewData['driftCount']);
        $this->assertSame($baselineFailed + 1, $viewData['failedCount']);
        $this->assertSame('failed-site', $viewData['latestDriftLabel']);
        $this->assertSame(SiteResource::getUrl('webhooks', ['record' => $failedSite]), $viewData['latestDriftSiteUrl']);
        $this->assertStringContainsString('Status: Failed', $viewData['latestDriftSummary']);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
