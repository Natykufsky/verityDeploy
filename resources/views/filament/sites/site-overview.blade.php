@php
    $badges = array_values(array_filter([
        [
            'label' => $record->active ? 'active' : 'inactive',
            'color' => $record->active ? 'success' : 'gray',
        ],
        [
            'label' => ucfirst((string) $record->deploy_source),
            'color' => 'primary',
        ],
        filled($record->server?->name) ? [
            'label' => $record->server->name,
            'color' => 'slate',
        ] : null,
    ]));
@endphp

<details open class="deployment-frost-card rounded-3xl p-5">
    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                site overview
                <x-info-tooltip text="Core site identity and placement. Domain, SSL, release history, backups, and webhooks are managed in the sections below." label="Site overview help" />
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">{{ $record->name }}</h2>
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

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Server</span>
                <x-info-tooltip text="The server determines how the site is deployed, where files live, and which automation options are available." label="Server help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->server?->name ?? 'No server assigned' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->server?->connection_type ? ucfirst((string) $record->server->connection_type) . ' connection' : 'No connection type set yet.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Deployment path</span>
                <x-info-tooltip text="This is the root folder used for releases and runtime files. It is the main deployment target for the site." label="Deployment path help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->deploy_path ?? 'Not configured yet' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->deploy_source === 'git' ? 'Git deploys publish releases into this path.' : 'Local deploys extract the uploaded source into this path.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Runtime</span>
                <x-info-tooltip text="Runtime settings control the shared environment file, PHP version, and site-level variables." label="Runtime help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->shared_env_mode === 'custom' ? 'Custom override' : 'Generated env' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->php_version ?? 'PHP version not set' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Release state</span>
                <x-info-tooltip text="This shows whether the site currently has a live release deployed and serving traffic." label="Release state help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->current_release_status === 'active' ? 'active release' : 'no active release' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->last_successful_deploy_badge }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Next action</span>
                <x-info-tooltip text="This is a short operational hint based on the current site state, so you know where to go next." label="Next action help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">Use the sections below for domain, backups, releases, and webhooks.</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->active ? 'The site is active.' : 'The site is inactive.' }}</div>
        </div>
    </div>
</details>
