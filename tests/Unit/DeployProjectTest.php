<?php

namespace Tests\Unit;

use App\Actions\DeployProject;
use App\Jobs\DeployJob;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Services\Deployment\CpanelDeploymentRunner;
use App\Services\Deployment\FileTransportService;
use App\Services\Deployment\ReleaseManager;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DeployProjectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dispatch_creates_a_deployment_and_queues_a_job(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Deploy User',
            'email' => 'deploy@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Deploy Server',
            'ip_address' => '203.0.113.80',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'deploy-site',
            'deploy_path' => '/var/www/deploy-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/deploy-site.git',
            'default_branch' => 'main',
        ]);

        $deployProject = new DeployProject(
            Mockery::mock(SshCommandRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(FileTransportService::class)->shouldIgnoreMissing(),
            Mockery::mock(CpanelDeploymentRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(ReleaseManager::class)->shouldIgnoreMissing(),
        );

        $deployment = $deployProject->dispatch(
            $site,
            $user,
            'manual',
            'commit-abc123',
            'main'
        );

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'site_id' => $site->id,
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'pending',
            'branch' => 'main',
            'commit_hash' => 'commit-abc123',
        ]);
        $this->assertNotEmpty($deployment->release_path);

        Queue::assertPushed(DeployJob::class, fn (DeployJob $job): bool => $job->deploymentId === $deployment->id);
    }

    public function test_rollback_creates_a_rollback_deployment_and_queues_a_job(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Deploy User',
            'email' => 'rollback@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Rollback Server',
            'ip_address' => '203.0.113.81',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'rollback-site',
            'deploy_path' => '/var/www/rollback-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/rollback-site.git',
            'default_branch' => 'main',
        ]);

        $targetDeployment = $site->deployments()->create([
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'commit-rollback-target',
            'release_path' => '/var/www/rollback-site/releases/20260329010101-1',
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'exit_code' => 0,
        ]);

        $deployProject = new DeployProject(
            Mockery::mock(SshCommandRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(FileTransportService::class)->shouldIgnoreMissing(),
            Mockery::mock(CpanelDeploymentRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(ReleaseManager::class)->shouldIgnoreMissing(),
        );

        $deployment = $deployProject->rollback($targetDeployment, $user);

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'site_id' => $site->id,
            'triggered_by_user_id' => $user->id,
            'source' => 'rollback',
            'status' => 'pending',
            'branch' => 'main',
            'commit_hash' => 'commit-rollback-target',
            'release_path' => '/var/www/rollback-site/releases/20260329010101-1',
        ]);

        Queue::assertPushed(DeployJob::class, fn (DeployJob $job): bool => $job->deploymentId === $deployment->id);
    }

    public function test_rollback_requires_a_previous_release_path(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Deploy User',
            'email' => 'rollback-conflict@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Rollback Conflict Server',
            'ip_address' => '203.0.113.83',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'rollback-conflict-site',
            'deploy_path' => '/var/www/rollback-conflict-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/rollback-conflict-site.git',
            'default_branch' => 'main',
        ]);

        $targetDeployment = $site->deployments()->create([
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'commit_hash' => 'commit-no-release',
            'release_path' => null,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'exit_code' => 0,
        ]);

        $deployProject = new DeployProject(
            Mockery::mock(SshCommandRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(FileTransportService::class)->shouldIgnoreMissing(),
            Mockery::mock(CpanelDeploymentRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(ReleaseManager::class)->shouldIgnoreMissing(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have a release path');

        $deployProject->rollback($targetDeployment, $user);
    }

    public function test_failed_deploy_records_a_recovery_hint(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Deploy User',
            'email' => 'recovery@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Recovery Server',
            'ip_address' => '203.0.113.82',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'recovery-site',
            'deploy_path' => '/var/www/recovery-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/recovery-site.git',
            'default_branch' => 'main',
        ]);

        $ssh = Mockery::mock(SshCommandRunner::class);
        $ssh->shouldReceive('execute')
            ->once()
            ->andThrow(new RuntimeException('Permission denied (publickey).'));

        $deployProject = new DeployProject(
            $ssh,
            Mockery::mock(FileTransportService::class)->shouldIgnoreMissing(),
            Mockery::mock(CpanelDeploymentRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(ReleaseManager::class)->shouldIgnoreMissing(),
        );

        $deployment = $deployProject->dispatch($site, $user, 'manual', 'commit-recovery', 'main');

        try {
            $deployProject->run($deployment->fresh(['site.server', 'steps']));
            $this->fail('Expected the deployment to fail.');
        } catch (RuntimeException) {
            // Expected.
        }

        $deployment = $deployment->fresh();

        $this->assertSame('failed', $deployment->status);
        $this->assertSame('Permission denied (publickey).', $deployment->error_message);
        $this->assertNotEmpty($deployment->recovery_hint);
        $this->assertStringContainsString('SSH credentials', $deployment->recovery_hint);
        $this->assertStringContainsString('Recovery', $deployment->output);
    }
}
