<?php

namespace Tests\Unit;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Widgets\ServerHealthOverviewCard;
use App\Models\Server;
use App\Models\ServerHealthCheck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServerHealthOverviewCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_server_health_counts_and_latest_check_copy(): void
    {
        $baselineOnline = Server::query()->where('status', 'online')->count();
        $baselineOffline = Server::query()->where('status', 'offline')->count();
        $baselineError = Server::query()->where('status', 'error')->count();
        $baselineTotal = Server::query()->count();

        $server = Server::query()->create([
            'name' => 'Health Server',
            'ip_address' => '203.0.113.150',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'online',
            'metrics' => [
                'cpu_usage' => 0.18,
                'ram_usage' => '52%',
                'disk_free' => '74%',
                'uptime' => '2 days, 03:11',
            ],
        ]);

        Server::query()->create([
            'name' => 'Offline Server',
            'ip_address' => '203.0.113.151',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'offline',
        ]);

        Server::query()->create([
            'name' => 'Error Server',
            'ip_address' => '203.0.113.152',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'ssh_key',
            'status' => 'error',
        ]);

        $check = ServerHealthCheck::query()->create([
            'server_id' => $server->id,
            'status' => 'successful',
            'output' => 'uptime && free -m && df -h',
            'metrics' => [
                'cpu_usage' => 0.18,
                'ram_usage' => '52%',
                'disk_free' => '74%',
                'uptime' => '2 days, 03:11',
            ],
            'tested_at' => now(),
            'exit_code' => 0,
        ]);

        $widget = new ServerHealthOverviewCard();
        $viewData = $this->invokeProtected($widget, 'getViewData');

        $this->assertSame($baselineOnline + 1, $viewData['onlineCount']);
        $this->assertSame($baselineOffline + 1, $viewData['offlineCount']);
        $this->assertSame($baselineError + 1, $viewData['errorCount']);
        $this->assertSame($baselineTotal + 3, $viewData['totalCount']);
        $this->assertSame('Health Server', $viewData['latestCheckLabel']);
        $this->assertSame(ServerResource::getUrl('view', ['record' => $server]), $viewData['latestCheckUrl']);
        $this->assertStringContainsString('CPU 0.18', $viewData['latestCheckSummary']);
        $this->assertSame('emerald', $viewData['latestCheckTone']);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
