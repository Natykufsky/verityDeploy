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

### Site and Server Management

- Site forms now derive the deploy path from the selected domain and server
- The generated deploy path is shown to the user instead of being manually edited
- Server pages include live domain inventory, site links, and quick actions
- SSH key generation, authorization, and normalization are part of the server workflow
- Site-driven database requests are tracked as first-class site-linked records

### Database Tracking

- Site form requests now create or clear a site-linked database record
- The site details view now shows the requested database name, sync time, and notes
- The record is intentionally lightweight for now, with live cPanel provisioning left for a later pass

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

## Last Updated

April 28, 2026
