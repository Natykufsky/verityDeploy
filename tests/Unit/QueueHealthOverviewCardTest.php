<?php

namespace Tests\Unit;

use App\Filament\Widgets\QueueHealthOverviewCard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueHealthOverviewCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_queue_and_failed_job_counts(): void
    {
        $baselineQueued = DB::table('jobs')->count();
        $baselineFailed = DB::table('failed_jobs')->count();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'ProcessQueuedJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'high_priority',
            'payload' => json_encode(['displayName' => 'ProcessPriorityJob']),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'redis',
            'queue' => 'high_priority',
            'payload' => json_encode(['displayName' => 'SendAlertJob']),
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);

        $widget = new QueueHealthOverviewCard;

        $this->assertSame($baselineQueued + 2, $this->invokeProtected($widget, 'pendingJobsCount'));
        $this->assertSame(1, $this->invokeProtected($widget, 'reservedJobsCount'));
        $this->assertSame($baselineFailed + 1, $this->invokeProtected($widget, 'failedJobsCount'));
        $this->assertStringContainsString('High Priority', $this->invokeProtected($widget, 'latestFailedJobSummary', [$this->invokeProtected($widget, 'latestFailedJob')]));
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
