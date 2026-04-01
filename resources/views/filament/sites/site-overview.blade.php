@php
    $nextAction = match (true) {
        blank($record->primary_domain) => 'Set the primary domain and preview the host map before the first deploy.',
        in_array($record->ssl_state, ['unconfigured', 'pending', 'failed', 'expired'], true) && $record->force_https => 'Finish SSL issuance before forcing HTTPS redirects.',
        in_array($record->ssl_state, ['unconfigured', 'pending', 'failed', 'expired'], true) => 'Review the SSL state and decide whether HTTPS should be enforced.',
        ($record->server?->dns_provider ?? 'manual') === 'cloudflare' && ! filled($record->server?->dns_api_token) => 'Add the Cloudflare API token before provisioning DNS records.',
        ($record->server?->dns_provider ?? 'manual') === 'cloudflare' && ! filled($record->server?->dns_zone_id) => 'Add or discover the Cloudflare zone before provisioning DNS records.',
        ($record->server?->connection_type ?? null) === 'cpanel' && $record->cpanel_deploy_status === 'needs setup' => 'Run the cPanel site wizard before the first deploy.',
        filled($record->shared_env_contents) => 'Review the custom .env override before deploying again.',
        $record->current_release_status !== 'active' => 'Bootstrap the deploy path so the first release can go live.',
        $record->github_webhook_drift => 'Refresh the GitHub webhook status and re-provision if needed.',
        $record->backup_status !== 'healthy' => 'Create a fresh backup before the next deployment.',
        default => 'Deploy the latest changes when you are ready.',
    };

    $badges = array_values(array_filter([
        [
            'label' => $record->active ? 'active' : 'inactive',
            'color' => $record->active ? 'success' : 'gray',
        ],
        [
            'label' => ucfirst((string) $record->deploy_source),
            'color' => 'primary',
        ],
        [
            'label' => $record->domain_badge,
            'color' => $record->domain_status === 'ready' ? 'success' : 'warning',
        ],
        [
            'label' => $record->ssl_badge,
            'color' => in_array($record->ssl_state, ['valid', 'issued', 'active'], true)
                ? 'success'
                : (in_array($record->ssl_state, ['pending'], true) ? 'info' : (in_array($record->ssl_state, ['expired', 'failed'], true) ? 'danger' : 'warning')),
        ],
        [
            'label' => $record->force_https_badge,
            'color' => $record->force_https ? 'success' : 'gray',
        ],
        [
            'label' => $record->dns_badge,
            'color' => $record->dns_badge === 'cloudflare' ? 'info' : 'gray',
        ],
        [
            'label' => $record->current_release_status,
            'color' => $record->current_release_status === 'active' ? 'success' : 'gray',
        ],
        [
            'label' => $record->shared_env_badge,
            'color' => $record->shared_env_mode === 'custom' ? 'warning' : 'success',
        ],
        $record->server?->connection_type === 'cpanel'
            ? [
                'label' => $record->cpanel_deploy_status,
                'color' => $record->cpanel_deploy_status === 'ready' ? 'success' : 'warning',
            ]
            : null,
    ]));
@endphp

<details open class="deployment-frost-card rounded-3xl p-5">
    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                Site overview
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">{{ $record->name }}</h2>
                <p class="max-w-3xl text-sm leading-7 text-slate-300 md:text-base">
                    {{ $record->shared_env_summary }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @foreach ($badges as $badge)
                <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ match ($badge['color']) {
                    'success' => 'bg-emerald-500/15 text-emerald-300',
                    'warning' => 'bg-amber-500/15 text-amber-300',
                    'danger' => 'bg-rose-500/15 text-rose-300',
                    'info' => 'bg-sky-500/15 text-sky-300',
                    'primary' => 'bg-cyan-500/15 text-cyan-300',
                    default => 'bg-white/5 text-slate-300',
                } }}">
                    {{ $badge['label'] }}
                </span>
            @endforeach
        </div>
    </summary>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Server</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->server?->name ?? 'No server assigned' }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ $record->server?->connection_type ? ucfirst((string) $record->server->connection_type) . ' connection' : 'No connection type set yet.' }}
            </p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Domain setup</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->primary_domain ?? 'No primary domain yet' }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ $record->domain_summary }}
            </p>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div><span class="font-semibold text-white">SSL:</span> {{ $record->ssl_summary }}</div>
                <div><span class="font-semibold text-white">HTTPS redirect:</span> {{ $record->force_https_summary }}</div>
                <div><span class="font-semibold text-white">DNS:</span> {{ $record->dns_provider_summary }}</div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Next action</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $nextAction }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ $record->current_release_status === 'active' ? 'This site is ready for the next deployment cycle.' : 'Start with the setup step that removes the current blocker.' }}
            </p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Current release</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->current_release_path ?? 'Not active yet' }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ $record->last_successful_deploy_badge }}
            </p>
        </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Deployments</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ $record->deployments()->count() }}</div>
        </div>
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Webhooks</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->github_webhook_health }}</div>
            <p class="mt-1 text-sm text-slate-400">{{ $record->github_webhook_sync_health }}</p>
        </div>
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Runtime</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->shared_env_mode === 'custom' ? 'Custom override' : 'Generated env' }}</div>
            <p class="mt-1 text-sm text-slate-400">{{ $record->php_version ?? 'PHP version not set' }}</p>
        </div>
    </div>
</details>
