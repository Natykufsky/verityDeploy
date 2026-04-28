<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Models\SiteTerminalRun;
use App\Models\User;
use App\Services\Processes\SiteProcessService;
use App\Services\SSH\SshCommandRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class SiteProcessServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_restarts_queue_workers_and_records_the_run(): void
    {
        $user = User::query()->create([
            'name' => 'Process User',
            'email' => 'process@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Process Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'provider_type' => 'local',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'process-site',
            'deploy_path' => '/var/www/process-site',
            'current_release_path' => '/var/www/process-site/current',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/process-site.git',
            'default_branch' => 'main',
        ]);

        $runner = Mockery::mock(SshCommandRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), Mockery::on(fn (array $commands): bool => $commands === [
                'cd "/var/www/process-site/current"',
                'php artisan queue:restart',
            ]))
            ->andReturn([
                'output' => 'queue restarted',
                'exit_code' => 0,
            ]);

        $service = new SiteProcessService($runner);

        $run = $service->run($site->fresh(['server']), 'queue_restart', $user);

        $this->assertInstanceOf(SiteTerminalRun::class, $run);
        $this->assertSame('successful', $run->status);
        $this->assertSame('queue restarted', $run->output);
        $this->assertSame('queue_restart:cd "/var/www/process-site/current" && php artisan queue:restart', $run->command);
    }

    public function test_it_checks_daemon_status_and_records_the_run(): void
    {
        $user = User::query()->create([
            'name' => 'Daemon User',
            'email' => 'daemon@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Daemon Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'provider_type' => 'local',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'daemon-site',
            'deploy_path' => '/var/www/daemon-site',
            'current_release_path' => '/var/www/daemon-site/current',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/daemon-site.git',
            'default_branch' => 'main',
        ]);

        $runner = Mockery::mock(SshCommandRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn (Server $receivedServer): bool => $receivedServer->is($server)), Mockery::on(fn (array $commands): bool => count($commands) === 1 && str_contains($commands[0], 'supervisorctl status') && str_contains($commands[0], 'horizon:status') && str_contains($commands[0], 'queue workers not detected')))
            ->andReturn([
                'output' => "== supervisor ==\nsupervisorctl not installed\n== horizon ==\nhorizon not configured or unavailable\n== queue workers ==\nqueue workers not detected",
                'exit_code' => 0,
            ]);

        $service = new SiteProcessService($runner);

        $run = $service->run($site->fresh(['server']), 'daemon_status', $user);

        $this->assertInstanceOf(SiteTerminalRun::class, $run);
        $this->assertSame('successful', $run->status);
        $this->assertStringContainsString('daemon_status:', $run->command);
        $this->assertStringContainsString('cd "/var/www/daemon-site/current"', $run->command);
        $this->assertStringContainsString('supervisorctl status', $run->command);
        $this->assertStringContainsString('php artisan horizon:status', $run->command);
        $this->assertStringContainsString('queue workers not detected', $run->command);
    }
}
