# verityDeploy

verityDeploy is a Laravel + Filament dashboard for managing server infrastructure, site deployments, domain provisioning, webhooks, and cPanel workflows.

## Key Features

- Filament admin UI for servers, sites, domains, and deployment releases
- cPanel domain sync and import for primary/addon/alias/subdomains
- SSH and server provisioning with web-based key management
- Webhook delivery and operational alert handling
- Built-in queue processing, terminal bridges, and server metrics integration

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL / MariaDB, SQLite, or compatible database
- Optional: cPanel server access for live domain sync and provisioning

## Quick Start

1. Clone the repository.
2. Run `composer install`.
3. Copy the environment file:
   `cp .env.example .env`
4. Configure database, queue, and integration credentials in `.env`.
5. Generate the application key:
   `php artisan key:generate`
6. Run database migrations:
   `php artisan migrate`
7. Install Node dependencies:
   `npm install`
8. Start the local development stack:
   `composer dev`

> `composer dev` starts the Laravel app, queue worker, log watcher, and Vite dev server together.

## Local Development

- `composer dev` — full local development environment
- `npm run dev` — Vite asset builder
- `npm run build` — production asset build
- `php artisan serve` — run the web server
- `php artisan queue:work` — process queued jobs

## Configuration

Update `.env` with your environment values, including:

- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION`
- `APP_URL`
- cPanel credentials and API settings for server integrations
- GitHub / webhook credentials for deployment automation

## Documentation

- [Laravel Forge Audit & Roadmap](docs/laravel-forge-audit.md) - Feature comparison and improvement plan
- [Production setup guide](docs/production-setup.md)
- [Minimum deployment checklist](docs/minimum-deployment-checklist.md)
- [SSH terminal upgrade plan](docs/ssh-terminal-upgrade-plan.md)

## Testing

- Run unit and feature tests with:
  `phpunit`
- Format PHP code with:
  `vendor/bin/pint`

## Contributing

Contributions are welcome. Please open issues or pull requests for bug fixes and improvements.

## License

verityDeploy is open source and licensed under the MIT License.
