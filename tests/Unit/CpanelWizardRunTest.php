<?php

namespace Tests\Unit;

use App\Actions\BootstrapDeployPath;
use App\Models\CpanelWizardRun;
use App\Models\Server;
use App\Models\ServerConnectionTest;
use App\Models\Site;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Cpanel\CpanelWizardRunner;
use App\Services\Server\ServerProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CpanelWizardRunTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_exposes_readable_labels_and_state_helpers(): void
    {
        $run = CpanelWizardRun::query()->create([
            'wizard_type' => 'site_bootstrap',
            'status' => 'successful',
            'started_at' => now()->subMinutes(15),
            'finished_at' => now()->subMinutes(12),
            'steps' => [],
        ]);

        $this->assertSame('Site wizard', $run->wizard_type_label);
        $this->assertSame('success', $run->status_color);
        $this->assertTrue($run->isSuccessful());
        $this->assertFalse($run->isFailed());
        $this->assertNotEmpty($run->started_at_label);
    }

    public function test_server_checks_are_persisted_as_audit_history_run(): void
    {
        $server = Server::query()->create([
            'name' => 'Audit Server',
            'ip_address' => '203.0.113.55',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'pending',
        ]);

        $runner = $this->makeServerChecksRunner(port: 2222);

        $steps = $runner->runServerChecks($server);

        $this->assertCount(6, $steps);
        $this->assertSame(2222, $server->fresh()->ssh_port);
        $this->assertSame('online', $server->fresh()->status);
        $this->assertSame(
            1,
            CpanelWizardRun::query()
                ->where('server_id', $server->id)
                ->where('wizard_type', 'server_checks')
                ->count(),
        );

        $run = CpanelWizardRun::query()
            ->where('server_id', $server->id)
            ->where('wizard_type', 'server_checks')
            ->firstOrFail();

        $this->assertSame('server_checks', $run->wizard_type);
        $this->assertSame('successful', $run->status);
        $this->assertCount(6, $run->steps);
        $this->assertStringContainsString('discover ssh port', strtolower((string) $run->summary));
        $this->assertSame($server->id, $run->server_id);
        $this->assertNull($run->site_id);
    }

    public function test_site_bootstrap_records_a_single_audit_run(): void
    {
        $server = Server::query()->create([
            'name' => 'Audit Server',
            'ip_address' => '203.0.113.56',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'pending',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'audit-site',
            'deploy_path' => '/home/demo/audit-site',
            'deploy_source' => 'local',
            'local_source_path' => 'C:/workspace/audit-site',
        ]);

        $runner = $this->makeSiteBootstrapRunner(
            port: 2223,
            bootstrapSummary: [
                'Workspace ready.',
                'Shared runtime linked.',
            ],
        );

        $steps = $runner->runSiteBootstrap($site);

        $this->assertCount(8, $steps);
        $this->assertSame(2223, $server->fresh()->ssh_port);
        $this->assertSame(
            1,
            CpanelWizardRun::query()
                ->where('server_id', $server->id)
                ->where('site_id', $site->id)
                ->where('wizard_type', 'site_bootstrap')
                ->count(),
        );

        $run = CpanelWizardRun::query()
            ->where('server_id', $server->id)
            ->where('site_id', $site->id)
            ->where('wizard_type', 'site_bootstrap')
            ->firstOrFail();

        $this->assertSame('site_bootstrap', $run->wizard_type);
        $this->assertSame('successful', $run->status);
        $this->assertCount(8, $run->steps);
        $this->assertSame($server->id, $run->server_id);
        $this->assertSame($site->id, $run->site_id);
        $this->assertStringContainsString('Bootstrap deploy path', (string) $run->summary);
    }

    public function test_failed_server_checks_store_recovery_guidance(): void
    {
        $server = Server::query()->create([
            'name' => 'Broken Server',
            'ip_address' => '203.0.113.57',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'token',
            'status' => 'pending',
        ]);

        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('discoverSshPort')
            ->once()
            ->andThrow(new \RuntimeException('cPanel SSH token expired.'));

        $serverProvisioner = Mockery::mock(ServerProvisioner::class);
        $serverProvisioner->shouldIgnoreMissing();

        $bootstrapDeployPath = Mockery::mock(BootstrapDeployPath::class);
        $bootstrapDeployPath->shouldIgnoreMissing();

        $runner = new CpanelWizardRunner($client, $serverProvisioner, $bootstrapDeployPath);

        try {
            $runner->runServerChecks($server);
            $this->fail('Expected the wizard run to fail.');
        } catch (\RuntimeException) {
            // Expected.
        }

        $run = CpanelWizardRun::query()->latest('id')->firstOrFail();

        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->recovery_hint);
        $this->assertStringContainsString('cPanel API token', $run->recovery_hint);
        $this->assertStringContainsString('Recovery guidance', (string) $run->summary);
    }

    protected function makeServerChecksRunner(int $port): CpanelWizardRunner
    {
        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('discoverSshPort')
            ->once()
            ->andReturn($port);
        $client->shouldReceive('ping')
            ->once()
            ->andReturn(['status' => 'ok']);

        $serverProvisioner = Mockery::mock(ServerProvisioner::class);
        $serverProvisioner->shouldReceive('preflight')
            ->once()
            ->andReturn($this->makePreflightResult('Server provisioning preflight completed.'));

        $bootstrapDeployPath = Mockery::mock(BootstrapDeployPath::class);
        $bootstrapDeployPath->shouldIgnoreMissing();

        return new CpanelWizardRunner($client, $serverProvisioner, $bootstrapDeployPath);
    }

    protected function makeSiteBootstrapRunner(int $port, array $bootstrapSummary): CpanelWizardRunner
    {
        $client = Mockery::mock(CpanelApiClient::class);
        $client->shouldReceive('discoverSshPort')
            ->once()
            ->andReturn($port);
        $client->shouldReceive('ping')
            ->once()
            ->andReturn(['status' => 'ok']);

        $serverProvisioner = Mockery::mock(ServerProvisioner::class);
        $serverProvisioner->shouldReceive('preflight')
            ->once()
            ->andReturn($this->makePreflightResult('Server provisioning preflight completed.'));

        $bootstrapDeployPath = Mockery::mock(BootstrapDeployPath::class);
        $bootstrapDeployPath->shouldReceive('bootstrapAfterPreflight')
            ->once()
            ->andReturn($bootstrapSummary);

        return new CpanelWizardRunner($client, $serverProvisioner, $bootstrapDeployPath);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function makePreflightResult(string $output): ServerConnectionTest
    {
        $test = new ServerConnectionTest;
        $test->forceFill([
            'output' => $output,
        ]);

        return $test;
    }
}
