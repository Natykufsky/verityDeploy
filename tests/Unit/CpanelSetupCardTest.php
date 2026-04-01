<?php

namespace Tests\Unit;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Widgets\CpanelSetupCard;
use App\Models\CpanelWizardRun;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CpanelSetupCardTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('overallStateProvider')]
    public function test_it_resolves_the_overall_state(string $expected, ?string $serverStatus, ?string $siteStatus): void
    {
        $widget = new CpanelSetupCard;

        $serverRun = $serverStatus ? new CpanelWizardRun(['status' => $serverStatus]) : null;
        $siteRun = $siteStatus ? new CpanelWizardRun(['status' => $siteStatus]) : null;

        $this->assertSame($expected, $this->invokeProtected($widget, 'overallState', [$serverRun, $siteRun]));
    }

    public static function overallStateProvider(): array
    {
        return [
            ['No runs yet', null, null],
            ['Needs attention', 'failed', 'successful'],
            ['In progress', 'running', 'successful'],
            ['Healthy', 'successful', 'successful'],
            ['Partial setup', 'successful', null],
        ];
    }

    public function test_it_maps_statuses_to_colored_badges_and_tones(): void
    {
        $widget = new CpanelSetupCard;

        $successful = new CpanelWizardRun(['status' => 'successful']);
        $failed = new CpanelWizardRun(['status' => 'failed']);
        $running = new CpanelWizardRun(['status' => 'running']);

        $this->assertSame('Healthy', $this->invokeProtected($widget, 'runBadge', [$successful]));
        $this->assertSame('Failed', $this->invokeProtected($widget, 'runBadge', [$failed]));
        $this->assertSame('Running', $this->invokeProtected($widget, 'runBadge', [$running]));
        $this->assertSame('No run', $this->invokeProtected($widget, 'runBadge', [null]));

        $this->assertSame('emerald', $this->invokeProtected($widget, 'runTone', [$successful]));
        $this->assertSame('rose', $this->invokeProtected($widget, 'runTone', [$failed]));
        $this->assertSame('amber', $this->invokeProtected($widget, 'runTone', [$running]));
        $this->assertSame('slate', $this->invokeProtected($widget, 'runTone', [null]));

        $this->assertSame('slate', $this->invokeProtected($widget, 'auditTone', [0]));
        $this->assertSame('amber', $this->invokeProtected($widget, 'auditTone', [2]));
        $this->assertSame('emerald', $this->invokeProtected($widget, 'auditTone', [4]));
    }

    public function test_it_navigates_to_the_latest_server_or_site_wizard(): void
    {
        $server = Server::query()->create([
            'name' => 'Nav Server',
            'ip_address' => '203.0.113.120',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'nav-site',
            'deploy_path' => '/home/demo/nav-site',
            'deploy_source' => 'local',
            'local_source_path' => 'C:/workspace/nav-site',
        ]);

        $olderServerRun = CpanelWizardRun::query()->create([
            'server_id' => $server->id,
            'wizard_type' => 'server_checks',
            'status' => 'successful',
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(19),
            'steps' => [],
            'summary' => 'Server run.',
        ]);

        $latestSiteRun = CpanelWizardRun::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'wizard_type' => 'site_bootstrap',
            'status' => 'successful',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'steps' => [],
            'summary' => 'Site run.',
        ]);

        $widget = new CpanelSetupCard;

        $this->assertSame(
            ServerResource::getUrl('cpanel-wizard', ['record' => $server]),
            $widget->openServerRun()->getTargetUrl(),
        );

        $this->assertSame(
            SiteResource::getUrl('cpanel-bootstrap-wizard', ['record' => $site]),
            $widget->openSiteRun()->getTargetUrl(),
        );

        $this->assertSame(
            SiteResource::getUrl('cpanel-bootstrap-wizard', ['record' => $site]),
            $widget->openLatestRun()->getTargetUrl(),
        );
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
