@php
    $server = $record->server;
    $site = $record->site;
    $scheme = ($site?->force_https || in_array((string) ($site?->ssl_state ?? ''), ['valid', 'issued', 'active', 'installed'], true)) ? 'https' : 'http';
    $liveDomain = $site?->currentDomain?->name ?? $site?->primary_domain ?? $record->name;
@endphp

<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <p class="text-xs uppercase tracking-[0.28em] text-slate-400">domain overview</p>
                <x-info-tooltip text="This page centers on the domain record, the linked site, and the server that manages it." label="Domain overview help" />
            </div>
            <h3 class="text-lg font-semibold tracking-tight text-white">{{ $record->name }}</h3>
            <p class="text-sm leading-6 text-slate-300">
                This is the domain record managed by the server-side inventory.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                {{ ucfirst((string) ($record->type ?: 'domain')) }}
            </span>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                {{ $record->is_active ? 'active' : 'inactive' }}
            </span>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                {{ $record->is_ssl_enabled ? 'ssl enabled' : 'ssl off' }}
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Server</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $server?->name ?? 'Unassigned' }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $server?->provider_label ?? 'No server linked' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Site</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $site?->name ?? 'Unassigned' }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $site?->deploy_source ? ucfirst((string) $site->deploy_source) . ' deployment' : 'No deployment source set' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Live site</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $liveDomain }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $scheme }}://{{ $liveDomain }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">SSL</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->ssl_status ?: 'not configured' }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $record->ssl_expires_at?->format('M d, Y H:i') ?? 'no expiry set' }}</div>
        </div>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Routing</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>cPanel document root: <span class="font-semibold text-white">{{ $record->web_root ?: 'not set' }}</span></div>
                <div>PHP version: <span class="font-semibold text-white">{{ $record->php_version ?: 'inherit' }}</span></div>
                <div>External ID: <span class="font-semibold text-white">{{ $record->external_id ?: 'n/a' }}</span></div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Quick checks</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>Open the live site from the header action to verify traffic.</div>
                <div>Use the Mirror tab to inspect the linked site and DNS snapshot.</div>
                <div>Update SSL or active status from this record when the server changes.</div>
            </div>
        </div>
    </div>
</div>
