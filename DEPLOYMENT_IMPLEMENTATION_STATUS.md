# VerityDeploy Implementation Status

## Overview

verityDeploy is an active Laravel + Filament control panel for managing servers, sites, domains, deployments, webhooks, and cPanel workflows.

As of April 28, 2026, the app is beyond the original "ready to deploy" milestone and now includes live deployment progress streaming, cPanel domain synchronization, domain docroot management, and a more guided deployment UI.

## What Is Implemented

### Deployment Management

- Create, resume, retry, redeploy, and rollback deployments from the admin UI
- View deployment history with step-by-step progress and live log output
- Refresh deployment records manually from the deployment view page
- Jump between deployments from a searchable selector on the deployment view page
- Mark failed queue jobs as failed instead of leaving them stuck in `running`

### Live Deployment Updates

- Deployment logs and progress now stream over a websocket bridge
- The deployment page also supports a manual refresh fallback
- The bridge is exposed through the `deployment:bridge` artisan command
- Browser-side deployment streaming is wired into Filament pages through an inline asset

### cPanel and Domain Management

- Live cPanel domain inventory sync is available from the server page
- cPanel docroot updates are managed from the domain workflow
- Domain CRUD actions sync to cPanel where supported
- Site creation links the site to the server and domain flow
- Live site docroots follow the current release path where the deployment flow expects it
- SSL status can be refreshed from the site page and HTTPS redirects can be synced back to cPanel
- SSL actions now emit operational alerts so inbox, email, and webhook subscribers can see the change
- Domain pages now expose manual SSL certificate tracking, renewal state, and a server-side renewal scan action

### Site and Server Management

- Site forms now derive the deploy path from the selected domain and server
- The generated deploy path is shown to the user instead of being manually edited
- Server pages include live domain inventory, site links, and quick actions
- SSH key generation, authorization, and normalization are part of the server workflow
- Site process actions now include queue restart, Horizon terminate, supervisor restart, daemon status checks, and daemon stack recovery
- Site-driven database requests are tracked as first-class site-linked records
- The site page now includes a file browser/editor for the current release path
- The site page now includes SSL status, AutoSSL refresh, and HTTPS redirect sync actions

### Database Tracking

- Site form requests now create or clear a site-linked database record
- The site details view now shows the requested database name, live cPanel identifiers, sync time, and notes
- Databases have a first-class management resource with live provisioning and live removal actions
- cPanel database provisioning uses the MySQL UAPI to create the database, create the user, and grant privileges
- Database passwords are stored encrypted and reused for provisioning unless a new one is entered

### Backups and Restore

- Site backups are created from the current release into the backups directory
- Restores copy the selected snapshot into a new release and reactivate the site
- Backup verification checks the stored snapshot path and integrity metadata
- Backup records are available in their own Filament resource for browsing and manual restore actions
- The site view still provides the backup shortcuts and history preview for the current site
- Sites now carry a backup policy with an enable toggle, schedule label, and retention count
- Older successful backup snapshots are pruned automatically after a new backup completes

### Scheduled Jobs

- Scheduled jobs are managed through a Filament resource
- The resource is compatible with the current Filament schema API
- The app boots cleanly with the scheduled-job resource enabled

## Current Operational Requirements

To use the deployment features effectively:

1. Configure the target server with valid SSH credentials
2. Configure cPanel credentials if the server is cPanel-backed
3. Ensure the deployment bridge is running for live progress updates
4. Keep queue workers running so deployment jobs can process
5. Confirm the live cPanel docroot points at the current release entry point

## Current Gaps

- Background daemon and process management are visible and partially controllable from the UI, but deeper automation, health checks, and recovery heuristics are still pending
- Manual SSL certificate upload and renewal tracking are implemented for domains, but full automated certificate issuance is still cPanel-first
- Monitoring and alerting can still be expanded beyond the current deployment, server, webhook, and SSL events

## Current Notes

- Deployment progress is no longer polling-only
- The deployment view now includes a direct deployment selector
- The domain and server views are the main place to inspect live cPanel state
- The old standalone domain view page is no longer part of the UI

## Documentation Map

- [`README.md`](README.md) - project overview and setup
- [`docs/production-setup.md`](docs/production-setup.md) - production deployment notes
- [`docs/minimum-deployment-checklist.md`](docs/minimum-deployment-checklist.md) - launch checklist
- [`docs/ssh-terminal-upgrade-plan.md`](docs/ssh-terminal-upgrade-plan.md) - terminal/SSH roadmap
- [`docs/laravel-forge-audit.md`](docs/laravel-forge-audit.md) - comparison and planning notes

## Status Summary

- Deployment tooling: implemented
- Live deployment streaming: implemented
- cPanel domain inventory sync: implemented
- cPanel docroot management: implemented
- Site/server/domain admin flow: implemented
- Scheduled jobs resource: implemented
- SSL refresh and HTTPS redirect sync: implemented
- Site process controls: implemented

## Last Updated

April 28, 2026
