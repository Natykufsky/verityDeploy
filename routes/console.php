<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanupStaleReleases;
use App\Jobs\DispatchServerHealthChecks;
use App\Jobs\RefreshWebhookStatuses;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DispatchServerHealthChecks())
    ->hourly()
    ->name('veritydeploy:dispatch-server-health-checks');

Schedule::job(new RefreshWebhookStatuses())
    ->hourly()
    ->name('veritydeploy:refresh-webhook-statuses');

Schedule::job(new CleanupStaleReleases())
    ->dailyAt('03:30')
    ->name('veritydeploy:cleanup-stale-releases');
