<?php

namespace Tests\Unit;

use App\Filament\Widgets\SchedulerHealthOverviewCard;
use App\Models\ScheduledJob;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SchedulerHealthOverviewCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_scheduler_counts_and_next_runs(): void
    {
        $server = Server::query()->create([
            'name' => 'Scheduler Server',
            'ip_address' => '203.0.113.240',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'scheduler-site',
            'deploy_path' => '/var/www/scheduler-site',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/scheduler-site.git',
        ]);

        $dueJob = ScheduledJob::query()->create([
            'site_id' => $site->id,
            'command' => 'php artisan schedule:run',
            'frequency' => 'hourly',
            'description' => 'Run the app scheduler',
            'is_active' => true,
            'last_run_at' => now()->subHour(),
            'next_run_at' => now()->subMinute(),
        ]);

        ScheduledJob::query()->create([
            'site_id' => $site->id,
            'command' => 'php artisan queue:restart',
            'frequency' => 'daily',
            'description' => 'Restart workers',
            'is_active' => true,
            'last_run_at' => now()->subDay(),
            'next_run_at' => now()->addHour(),
        ]);

        ScheduledJob::query()->create([
            'site_id' => $site->id,
            'command' => 'php artisan horizon:terminate',
            'frequency' => 'weekly',
            'description' => 'Inactive job',
            'is_active' => false,
            'last_run_at' => now()->subDays(2),
            'next_run_at' => now()->addDays(2),
        ]);

        $widget = new SchedulerHealthOverviewCard;

        $this->assertSame(2, $this->invokeProtected($widget, 'activeJobsCount'));
        $this->assertSame(1, $this->invokeProtected($widget, 'dueNowJobsCount'));
        $this->assertSame($dueJob->id, $this->invokeProtected($widget, 'nextDueJob')->id);
        $this->assertStringContainsString($site->name, $this->invokeProtected($widget, 'nextRunSummary', [$dueJob]));
        $this->assertStringContainsString('schedule:run', $this->invokeProtected($widget, 'latestRunJob')->command);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
