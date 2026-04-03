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

    public function test_failed_job_marks_running_deployment_as_failed(): void
    {
        $user = User::query()->create([
            'name' => 'Deploy User',
            'email' => 'job-failure@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Job Failure Server',
            'ip_address' => '203.0.113.86',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'job-failure-site',
            'deploy_path' => '/var/www/job-failure-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/job-failure-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = $site->deployments()->create([
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'running',
            'branch' => 'main',
            'commit_hash' => 'commit-job-failure',
            'release_path' => '/var/www/job-failure-site/releases/20260401010101-1',
            'started_at' => now()->subMinutes(3),
        ]);

        $job = new DeployJob($deployment->id);

        $job->failed(new RuntimeException('Unexpected deployment crash.'));

        $deployment = $deployment->fresh();

        $this->assertSame('failed', $deployment->status);
        $this->assertNotNull($deployment->finished_at);
        $this->assertSame('Unexpected deployment crash.', $deployment->error_message);
    }

    public function test_local_source_resume_skips_reupload_when_archive_was_already_uploaded(): void
    {
        $server = Server::query()->create([
            'name' => 'Resume Server',
            'ip_address' => '203.0.113.84',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'password',
            'sudo_password' => 'secret',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'resume-site',
            'deploy_path' => '/var/www/resume-site',
            'deploy_source' => 'local',
            'local_source_path' => storage_path('app/testing/source'),
            'default_branch' => 'main',
        ]);

        $deployment = $site->deployments()->create([
            'triggered_by_user_id' => null,
            'source' => 'manual',
            'status' => 'failed',
            'branch' => 'main',
            'commit_hash' => null,
            'release_path' => '/var/www/resume-site/releases/20260330010101-1',
            'archive_uploaded_at' => now()->subMinutes(10),
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(5),
            'exit_code' => 1,
            'error_message' => 'Sync failed previously.',
        ]);

        $fileTransportService = Mockery::mock(FileTransportService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $fileTransportService->shouldNotReceive('packageLocalSourceArchive');
        $fileTransportService->shouldNotReceive('uploadArchive');

        $commands = $fileTransportService->transferCommands($deployment->fresh(['site.server']));

        $this->assertCount(1, $commands);
        $this->assertSame('Extract uploaded archive', $commands[0]['label']);
        $this->assertStringContainsString('/tmp/veritydeploy-'.$deployment->id.'.zip', $commands[0]['command']);
    }

    public function test_resume_requeues_the_same_deployment_record(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Resume User',
            'email' => 'resume@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'name' => 'Resume Deploy Server',
            'ip_address' => '203.0.113.85',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'resume-deploy-site',
            'deploy_path' => '/var/www/resume-deploy-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/resume-deploy-site.git',
            'default_branch' => 'main',
        ]);

        $deployment = $site->deployments()->create([
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'failed',
            'branch' => 'main',
            'commit_hash' => 'commit-resume',
            'release_path' => '/var/www/resume-deploy-site/releases/20260330020202-1',
            'archive_uploaded_at' => now()->subMinutes(3),
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(2),
            'exit_code' => 1,
            'error_message' => 'Previous deploy failed.',
        ]);

        $deployProject = new DeployProject(
            Mockery::mock(SshCommandRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(FileTransportService::class)->shouldIgnoreMissing(),
            Mockery::mock(CpanelDeploymentRunner::class)->shouldIgnoreMissing(),
            Mockery::mock(ReleaseManager::class)->shouldIgnoreMissing(),
        );

        $this->assertTrue($deployment->isResumable());

        $resumed = $deployProject->resume($deployment, $user);

        $this->assertSame($deployment->id, $resumed->id);
        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'status' => 'pending',
            'triggered_by_user_id' => $user->id,
            'release_path' => '/var/www/resume-deploy-site/releases/20260330020202-1',
        ]);

        Queue::assertPushed(DeployJob::class, fn (DeployJob $job): bool => $job->deploymentId === $deployment->id);
        $this->assertSame('pending', $resumed->fresh()->status);
    }
}
