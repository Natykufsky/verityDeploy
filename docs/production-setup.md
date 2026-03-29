# Production Setup

This guide covers the minimum production tasks for running verityDeploy safely.

## Server Setup

1. Install PHP 8.2 or later, Composer 2, and the extensions required by Laravel and Filament.
2. Point your web server at the `public/` directory.
3. Configure the `.env` file with the production database, queue, cache, and mail settings.
4. Run the migrations and seed only if you need demo data.
5. Generate or verify the application key before the first launch.

## cPanel Setup

1. Add a cPanel server in verityDeploy and choose `cPanel` as the connection type.
2. Save the SSH user, SSH port, and cPanel API token.
3. Use `Test API` to confirm the token works.
4. Use `Discover` to pull the SSH port from the cPanel API when needed.
5. Run the cPanel server wizard, then the site bootstrap wizard, before the first deploy.

## Queue Workers

1. Run a queue worker in production so deployment jobs and background health jobs are processed.
2. Keep the worker supervised with your process manager of choice.
3. Restart the worker after deploys so the code and worker stay in sync.

## Cron and Scheduler

1. Add one cron entry that runs Laravel’s scheduler every minute.
2. Let the scheduler dispatch health checks, webhook sync refreshes, and stale release cleanup.
3. Monitor the queue and scheduler logs for failed jobs.

## Suggested Cron Entry

```bash
* * * * * php /path/to/verityDeploy/artisan schedule:run >> /dev/null 2>&1
```

## GitHub Integration

1. Configure a GitHub PAT in App Settings, or connect GitHub OAuth from the settings page.
2. Use the webhook settings page to provision the repository webhook.
3. Keep webhook sync status healthy before relying on push-triggered deploys.
