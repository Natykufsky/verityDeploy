@php
    $preview = $preview ?? [];
@endphp

<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full {{ in_array($preview['ssl_state'] ?? 'unconfigured', ['valid', 'issued', 'active'], true) ? 'bg-emerald-300' : 'bg-amber-300' }}"></span>
                ssl preview
            </div>
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $preview['primary_domain'] ?? 'no primary domain configured yet' }}
            </h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-300">
                {{ $preview['message'] ?? 'Preview the ssl state for this site.' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['ssl_state'] ?? 'unconfigured' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['force_https'] ? 'https enforced' : 'http allowed' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['ssl_last_synced_at'] ?? 'never synced' }}
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">SSL state</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['ssl_summary'] ?? 'SSL has not been configured yet.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">HTTPS redirect</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['force_https_summary'] ?? 'HTTPS redirects are disabled.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Last synced</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['ssl_last_synced_at'] ?? 'never synced' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Last error</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['ssl_last_error'] ?? 'No SSL errors recorded.' }}</div>
        </div>
    </div>

    <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Configuration steps</div>
            <div class="mt-3 space-y-2">
                @forelse ($preview['steps'] ?? [] as $step)
                    <div class="flex items-start gap-2 text-sm text-slate-300">
                        <span class="mt-1 h-2 w-2 rounded-full bg-emerald-300"></span>
                        <span>{{ $step }}</span>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">No ssl steps available yet.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Notes</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>The current implementation generates a cPanel SSL certificate and records the site as ssl ready.</div>
                <div>Force HTTPS can be toggled separately once SSL is ready.</div>
                <div>Use the site overview card to see the current SSL state at a glance.</div>
            </div>
        </div>
    </div>
</div>
