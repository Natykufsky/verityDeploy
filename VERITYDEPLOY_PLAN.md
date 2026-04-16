# verityDeploy Plan

`verityDeploy` is a self-hosted deployment platform for PHP applications, inspired by Laravel Forge, but intentionally smaller and easier to control.

## Product Vision

Build a tool that lets a developer:

- Add a server over SSH.
- Create one or more apps on that server.
- Deploy code from GitHub, GitLab, or Bitbucket.
- Manage environment variables and common services.
- Restart workers, run deploy scripts, and issue SSL certificates.
- See logs, history, and server health in one place.

The primary outcome is simple and reliable deployments without having to log into the server manually every time.

## Core Principles

- Keep the first version focused on one developer or a small team.
- Prefer simple SSH-based automation before introducing agents or complex orchestration.
- Make every action visible through logs and audit history.
- Design for safety first, especially around secrets, deploy scripts, and server access.
- Avoid feature bloat until the deployment loop is dependable.

## Current Status

This plan started as the launch roadmap for `verityDeploy`. The codebase has moved well beyond the original MVP, so this section separates what is already built from what is still pending.

### Completed

- Laravel app, auth, dashboard shell, and Filament UI.
- Server management with SSH, connection tests, health checks, and provisioning helpers.
- Site/app management, deploy actions, release history, rollback, and release cleanup.
- Deployment logs, live terminal output, step detail views, and command snippets.
- Environment variable editing and shared file management.
- Webhook provisioning, GitHub OAuth/PAT support, and sync drift handling.
- cPanel setup, bootstrap, deploy, rollback, and wizard history tracking.
- Alerts inbox, dashboard alert widgets, and operational summary cards.
- External alert delivery to email and webhooks.
- Per-user alert preferences and alert delivery status logging.
- Team collaboration, team workspaces, and permission-aware resource access.
- CI, automated tests, production docs, and scheduled maintenance jobs.
- Project templates (Laravel, Node.js, Python, Static) with auto-configured settings.
- Multi-language deployment support with build commands and port configuration.
- Live domain synchronization from cPanel servers.
- Enhanced SSL management with auto-renewal options.
- Database setup and optional creation during site provisioning.
- Inline domain creation with type selection (addon/subdomain/alias).
- Improved server UI with sites overview and metrics.
- ZIP file upload support for local deployments.
- Scheduled jobs management UI (basic implementation).

### Still Pending

- Multiple server providers.
- Backups and restore workflows.
- External alert delivery to Slack or any additional destinations you want beyond email/webhooks.
- Custom deploy hooks before and after deploys.
- A lightweight agent for more reliable server communication.
- Additional live staging smoke tests for rollback and cPanel edge cases.
- Full scheduled jobs deployment integration (currently UI-only).
- Background daemon/process management.
- File manager and code editor integration.
- Advanced SSL certificate management (manual upload, renewal tracking).
- Database management interface.
- Monitoring & alerts expansion.

## MVP Definition

The first usable version should support:

- User registration and login.
- A dashboard showing servers and apps.
- Adding a server with SSH credentials.
- Verifying server connectivity.
- Creating an app/site on a server.
- Pulling code from Git and running a deploy script.
- Managing environment variables.
- Viewing deployment logs and status.
- Restarting queue workers and reloading services.
- Issuing a free SSL certificate with Let’s Encrypt.

If a user can connect one server, create one app, deploy code, and see logs, the MVP is working.

## What To Delay

These should wait until the core loop is stable:

- Team management and advanced roles.
- Billing and subscriptions.
- Multi-cloud provisioning.
- Auto-scaling or high availability orchestration.
- Kubernetes support.
- Advanced monitoring and alerting.
- Full rollback UI and release management.
- Multiple deployment strategies beyond a basic Git pull or release flow.

## Suggested Tech Stack

- Backend: Laravel.
- Frontend: Blade, Laravel + Livewire/Filament.
- Database: MySQL.
- Queue: Redis.
- Cache: Redis.
- Server connection: SSH.
- Spatie SSH: To handle the heavy lifting of remote command execution.
- Laravel Webhooks: To listen for "Push" events from GitHub.
- Action Classes: Create a DeployProject action that handles the logic regardless of whether the source is local or Git
- Background jobs: Laravel queue workers.
- File storage: local disk for development, S3-compatible storage if needed later.

If you want a fast path, a Laravel monolith is the cleanest starting point.

### Stack Guidance

- Use Laravel + Livewire + Filament v3 for the app shell and admin UI.
- Use Spatie SSH for remote command execution on managed servers.
- Use Laravel Webhooks to receive GitHub push events and start deployment flows.
- Use action classes such as `DeployProject` to centralize deployment orchestration.
- Keep source handling abstract so the same deployment action works for manual, local, or Git-triggered deploys.

## System Architecture

The app should be split into these logical layers:

- Web app layer for UI and API endpoints.
- Domain layer for servers, sites, deployments, and credentials.
- Queue worker layer for long-running actions.
- SSH execution layer for server commands.
- Logging and audit layer for traceability.
- Notification layer for failures and status changes.

The app should not execute heavy deployment tasks directly inside web requests.

## Main User Flows

### 1. Add a Server

- User clicks “Add Server”.
- User enters server name, IP address, and SSH access details.
- App tests the SSH connection.
- App verifies the server meets basic requirements.
- App stores the server record and marks it active.

### 2. Create an App

- User selects a server.
- User enters app name, repository URL, branch, and deploy path.
- User chooses runtime options like PHP version and web root.
- App stores the app record and deployment defaults.

### 3. Deploy Code

- User clicks deploy manually or a Git webhook triggers a deploy.
- App queues a deployment job.
- Worker connects to the server over SSH.
- Worker pulls code, installs dependencies, runs scripts, and refreshes services.
- App records each step and displays logs.

### 4. Manage Environment Variables

- User opens env settings for an app.
- User adds, edits, or removes variables.
- App writes the variables securely to the server.
- App records the change in audit logs.

### 5. Manage Services

- User restarts queue workers or reloads PHP-FPM and Nginx.
- App sends the command over SSH.
- App reports success or failure in the UI.

### 6. Enable SSL

- User requests certificate issuance.
- App configures the web server and runs Let’s Encrypt.
- App saves certificate metadata and renewal status.

## Data Model

You will likely need these tables:

- users
- teams
- servers
- server_credentials
- sites
- deployments
- deployment_steps
- environment_variables
- services
- ssh_keys
- audit_logs
- notifications
- server_health_checks

### Entity Notes

- users: login identity and ownership.
- teams: optional collaboration layer for later.
- servers: remote machines managed by the app.
- server_credentials: SSH auth material, encrypted at rest.
- sites: deployable apps tied to a server.
- deployments: one record per deploy attempt.
- deployment_steps: structured step-by-step log output.
- environment_variables: app-specific config secrets.
- services: service state and restart actions.
- ssh_keys: public keys for server access.
- audit_logs: security and action trail.
- notifications: deploy and health alerts.
- server_health_checks: last-known server status.

## Server Bootstrap Plan

The server onboarding flow should install and verify:

- SSH access.
- Git.
- PHP and required extensions.
- Composer.
- Nginx or another supported web server.
- Redis if queue support is enabled.
- A process manager for workers if needed.
- Let’s Encrypt tooling for SSL.

The bootstrap should be repeatable and idempotent where possible.

## Deployment Engine

The deployment engine is the heart of the app. It should support a predictable sequence such as:

- Pull latest code from the selected branch.
- Prepare a release directory or update the current working tree.
- Install PHP dependencies with Composer.
- Run build or compile steps if needed.
- Copy or write environment variables.
- Run database migrations if enabled.
- Clear or warm caches.
- Restart queue workers.
- Reload relevant services.

Every step should produce:

- A human-readable label.
- Start and end timestamps.
- A success or failure status.
- Output logs.
- Any command that failed.

If a deploy fails, the app should preserve enough context to understand exactly where it broke.

## Security Requirements

These should be built in from the start:

- Encrypt SSH credentials and sensitive environment values.
- Never store private keys or tokens in plain text.
- Use least privilege for server actions.
- Require confirmation for destructive actions.
- Record all administrative actions in audit logs.
- Protect webhook endpoints with signatures or shared secrets.
- Rate-limit login, SSH tests, and deploy actions.
- Validate all shell arguments carefully before command execution.

## Logging And Observability

The app should track:

- Deployment start and finish times.
- Failed steps and command output.
- Server connection failures.
- SSL issuance success or failure.
- Service restart outcomes.
- Health check results.
- User-triggered actions and their results.

You want to be able to answer:

- What happened?
- When did it happen?
- Who triggered it?
- What command was run?
- What part failed?

## Monitoring

Start simple and useful:

- Last heartbeat from each server.
- CPU, memory, and disk usage if available.
- Web server and queue worker status.
- Last successful deploy time.
- Failed deploy count in a recent window.

Avoid building a giant observability platform in the first release.

## User Interface Plan

The dashboard should include:

- Overview page with server and site cards.
- Server detail page with status, stats, and actions.
- Site detail page with deploy controls and logs.
- Deployment history page.
- Environment variable editor.
- Settings pages for SSH keys, webhooks, and notifications.

The interface should prioritize clarity over decoration.

## Filament Frontend Guide

Use Filament v5 as the primary frontend/admin framework for `verityDeploy`.

### Why Filament

- It gives you a fast path to a polished admin interface.
- It fits naturally with Laravel resources, actions, and infolists.
- It reduces the amount of custom CRUD UI you need to build.
- It keeps the product focused on deployment workflows instead of generic frontend complexity.
- It aligns better with the current Filament documentation and package support for a new project.

### Core UI Pattern

- Use Filament Resources for servers, sites, deployments, credentials, and notifications.
- Use Filament Pages for custom dashboard views and server detail screens.
- Use Infolists to present deployment logs, step-by-step output, and server metadata.
- Use custom Actions on the `Site` resource to trigger deployment jobs and other operational tasks.

### Deployment Log View

The deployment log view should:

- Show the latest deployment at the top or in a clear timeline.
- Render each deployment step with status, timestamps, and command output.
- Use an Infolist to display structured step data cleanly.
- Support live updates through either polling or a WebSocket listener.
- Keep the terminal-style output readable and easy to scan.

### Live Update Options

Pick one of these for refreshing deployment output:

- Polling interval: simplest to ship, reliable for MVP, and easy to debug.
- WebSocket listener: better real-time UX, but requires more infrastructure.

For the first version, polling is usually the safest default. You can move to WebSockets later if you want a more interactive terminal experience.

### Site Resource Actions

Add custom actions to the `Site` resource for common operations such as:

- Trigger deploy.
- Redeploy the latest commit.
- Restart queue workers.
- Refresh environment variables.
- Issue or renew SSL.

The deploy action should dispatch `DeployJob` instead of running the deploy inline inside the HTTP request.

### Filament Implementation Notes

- Keep the deployment logic in jobs and services, not inside Filament pages or actions.
- Let Filament handle presentation and user interaction.
- Use Notifications for success and failure feedback.
- Make sure actions are guarded with confirmation prompts where needed.
- Ensure the log refresh path can handle long-running deploys without freezing the UI.

## API And Job Design

Even if the UI is server-rendered, the internal operations should be job-driven.

- HTTP requests should validate input and enqueue work.
- Queue jobs should handle SSH execution and long-running commands.
- Jobs should update state as they progress.
- UI should poll or subscribe to status changes.
- Action classes should keep deployment logic reusable across UI-triggered and webhook-triggered deploys.

This keeps the app responsive and makes failures easier to recover from.

## Recommended Build Phases

### Phase 1: Foundation

- Create the Laravel app.
- Install and configure Filament v5.
- Set up authentication.
- Build the dashboard shell.
- Define the database schema.
- Add basic navigation and layout.

### Phase 2: Server Management

- Add server creation and editing.
- Implement SSH connectivity tests.
- Store server records and credentials securely.
- Show connection status in the UI.

### Phase 3: Site Management

- Add app/site creation.
- Store repo URL, branch, deploy path, and runtime settings.
- Show app details and basic actions.

### Phase 4: Deployment Engine

- Implement the queue-based deploy flow.
- Add deployment steps and log capture.
- Show deployment history and status.
- Support manual deploys first.
- Wire the Site resource deploy action to dispatch `DeployJob`.
- Add an Infolist-based deployment log view with polling or WebSocket updates.

### Phase 5: Environment And Services

- Add env variable management.
- Add worker restart and service reload actions.
- Add web server configuration changes if required.

### Phase 6: SSL And Health

- Add Let’s Encrypt issuance.
- Add certificate renewal logic.
- Add server heartbeat and health display.

### Phase 7: Reliability And Polish

- Add retries and better error messages.
- Improve audit logging.
- Add notifications for failures.
- Add guardrails for destructive actions.

## System Design Style

Use a modular monolith as the default architecture for `verityDeploy`.

### Design Goals

- Keep the codebase easy to understand and ship quickly.
- Reuse the same domain logic from the UI, webhook handlers, and background jobs.
- Avoid spreading deployment logic across controllers and view code.
- Make it easy to add new deployment sources later without rewriting the core flow.

### Structure Style

- Organize code by domain first, not just by Laravel layer.
- Keep operational workflows inside Action classes and Services.
- Use Jobs for long-running work and delayed execution.
- Use Events only when multiple parts of the app need to react to the same state change.

### Reusable Backend Components

- `DeployProject` action for all deploy entry points.
- `DeployJob` for queued execution.
- `SshCommandRunner` service for remote commands.
- `DeploymentLogger` service for consistent log formatting.
- `ServerBootstrapper` service for initial server setup.
- `WebhookHandler` classes for GitHub push events.
- `HealthCheck` service for server status checks.
- `AuditTrail` service for sensitive actions.

### Reusable UI Components

- `StatusBadge` for success, warning, and failure states.
- `ServerCard` for dashboard summaries.
- `SiteCard` for site summaries.
- `DeploymentTimeline` for step-by-step history.
- `TerminalLogViewer` for live command output.
- `ConfirmAction` patterns for dangerous operations.
- `KeyValueEditor` for environment variables.
- `EmptyState` blocks for empty dashboards and lists.

### Filament Reuse Strategy

- Use Resources for standard CRUD screens.
- Use Pages for custom dashboard and detail views.
- Use Infolists for logs and read-only data.
- Use Tables for history and lists.
- Use Actions for deploy, restart, SSL, and refresh tasks.

### Canonical Workflow Rules

- Use one canonical way to run remote commands.
- Use one canonical way to write deployment logs.
- Use one canonical action for deploy logic.
- Use one canonical status vocabulary: `pending`, `running`, `successful`, `failed`.
- Use one visual pattern for operational screens.

## Suggested Folder Structure

- `app/Domain/Servers`
- `app/Domain/Sites`
- `app/Domain/Deployments`
- `app/Domain/Environments`
- `app/Jobs`
- `app/Services/SSH`
- `app/Services/Deployments`
- `app/Services/Certificates`
- `app/Http/Controllers`
- `resources/views` or `resources/js`
- `database/migrations`
- `database/seeders`

Keeping deployment logic out of controllers will make the app easier to maintain.

## Acceptance Criteria For MVP

- A user can sign in.
- A user can add a server and verify it.
- A user can create a site on that server.
- A user can trigger a deploy.
- A user can view step-by-step logs.
- A user can update environment variables.
- A user can restart workers or services.
- A user can issue SSL for the site.
- Failed actions are visible and understandable.

## Risks To Watch

- SSH command safety and shell escaping issues.
- Secrets being stored or logged insecurely.
- Deploy steps taking too long inside a single job.
- Server bootstrap scripts becoming hard to support across distros.
- UI complexity growing faster than the backend reliability.
- Lack of rollback support causing fear during deploys.

## Rollback Strategy

Even if full rollback is not in v1, design for it early:

- Keep deployment history.
- Keep the last successful release information.
- Preserve command output from each step.
- Store enough metadata to restore a previous release later.

## Future Enhancements

- Team invitations and richer role matrices.
- Multiple server providers.
- Zero-downtime deploys with release directories and symlinks.
- Backups and restore workflows.
- Alerting to Slack or additional external destinations.
- Custom deploy hooks before and after deploys.
- Granular action-level permissions and approval workflows.
- A lightweight agent for more reliable server communication.

## Production Readiness Checklist

Before treating verityDeploy as production-ready, confirm the following:

1. Automated tests
- Run the unit and feature test suites in CI.
- Cover deploy, rollback, webhook, server health, and cPanel wizard persistence paths.

2. Operational safety
- Verify queue workers are supervised and restart cleanly.
- Verify failed deploys and failed wizard runs are visible in the UI and auditable.
- Confirm rollback only targets known successful releases.

3. Secrets and credentials
- Keep SSH keys, sudo passwords, and API tokens encrypted at rest.
- Ensure `.env` and host-specific secrets are never committed.

4. cPanel and server setup
- Validate cPanel token and port discovery against a live account.
- Confirm local-source packaging and Git source deploys both complete successfully.
- Confirm bootstrap and cleanup actions are idempotent.

5. Observability
- Make sure health checks and webhook sync refresh on a schedule.
- Confirm dashboard widgets show the latest server, site, and audit status.

6. Deployment hygiene
- Set a release retention policy and review cleanup behavior.
- Confirm rollback history survives normal deploy churn.

7. Documentation
- Document server onboarding.
- Document cPanel onboarding.
- Document queue worker and cron requirements.
- Document release cleanup and rollback procedures.

## Suggested Immediate Next Steps

- Historical note: these were the original starting steps.
- The app has already completed the foundation, onboarding, and deployment pipeline work.
- Use the current status and future enhancements sections below as the active backlog.

## Short Version

If you want the simplest summary: build `verityDeploy` as a Laravel app that manages servers, apps, deployments, env vars, and service restarts over SSH, with logs and health checks from day one.
