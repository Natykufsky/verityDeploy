<?php

namespace App\Jobs;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchServerHealthChecks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        Server::query()
            ->select('id')
            ->chunkById(100, function ($servers): void {
                foreach ($servers as $server) {
                    CheckServerHealth::dispatch((int) $server->id);
                }
            });
    }
}
