<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanupStaleReleases;
use App\Jobs\DispatchServerHealthChecks;
use App\Jobs\RefreshWebhookStatuses;
use App\Services\Terminal\TerminalWebSocketBridge;
use App\Services\ServerMetrics\ServerMetricsWebSocketBridge;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('terminal:bridge {--host=127.0.0.1} {--port=8787}', function (TerminalWebSocketBridge $bridge): int {
    $host = (string) $this->option('host');
    $port = (int) $this->option('port');

    $this->line(sprintf('starting terminal bridge on ws://%s:%d', $host, $port));

    $bridge->serve($host, $port);

    return 0;
})->purpose('Serve the websocket bridge for live SSH terminal sessions');

Artisan::command('server-metrics:bridge {--host=127.0.0.1} {--port=8788}', function (ServerMetricsWebSocketBridge $bridge): int {
    $host = (string) $this->option('host');
    $port = (int) $this->option('port');

    $this->line(sprintf('starting server metrics bridge on ws://%s:%d', $host, $port));

    $bridge->serve($host, $port);

    return 0;
})->purpose('Serve the websocket bridge for live server metric updates');

Schedule::job(new DispatchServerHealthChecks())
    ->hourly()
    ->name('veritydeploy:dispatch-server-health-checks');

Schedule::job(new RefreshWebhookStatuses())
    ->hourly()
    ->name('veritydeploy:refresh-webhook-statuses');

Schedule::job(new CleanupStaleReleases())
    ->dailyAt('03:30')
    ->name('veritydeploy:cleanup-stale-releases');
