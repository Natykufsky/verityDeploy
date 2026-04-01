@php
    $preview = $preview ?? [];
    $hostnames = array_values(array_filter($preview['hostnames'] ?? []));
@endphp

<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full {{ ($preview['status'] ?? 'needs setup') === 'ready' ? 'bg-emerald-300' : 'bg-amber-300' }}"></span>
                domain preview
            </div>
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ filled($preview['primary_domain'] ?? null) ? $preview['primary_domain'] : 'no primary domain configured yet' }}
            </h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-300">
                {{ $preview['message'] ?? 'Set a primary domain to preview the host map.' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ ($preview['status'] ?? 'needs setup') === 'ready' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">
                {{ $preview['status'] ?? 'needs setup' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['host_count'] ?? 0 }} hosts
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Primary domain</div>
            <div class="mt-2 break-all text-sm font-semibold text-white">
                {{ $preview['primary_domain'] ?? 'not set' }}
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Subdomains</div>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($preview['subdomains'] ?? [] as $subdomain)
                    <span class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-100">
                        {{ $subdomain }}
                    </span>
                @empty
                    <span class="text-sm text-slate-400">none yet</span>
                @endforelse
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Alias domains</div>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($preview['alias_domains'] ?? [] as $aliasDomain)
                    <span class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-100">
                        {{ $aliasDomain }}
                    </span>
                @empty
                    <span class="text-sm text-slate-400">none yet</span>
                @endforelse
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">SSL and HTTPS</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['ssl_badge'] ?? 'ssl unconfigured' }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-300">{{ $preview['ssl_summary'] ?? 'SSL has not been configured yet.' }}</p>
            <div class="mt-3 text-sm font-semibold text-white">{{ $preview['force_https_badge'] ?? 'disabled' }}</div>
            <p class="mt-1 text-sm leading-6 text-slate-400">{{ $preview['force_https_summary'] ?? 'HTTPS redirects are not enabled yet.' }}</p>
        </div>
    </div>

    <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Target summary</div>
            <p class="mt-2 text-sm leading-6 text-slate-300">{{ $preview['target_summary'] ?? 'The host map can be previewed once the primary domain is set.' }}</p>
            <p class="mt-3 text-sm leading-6 text-cyan-100">{{ $preview['config_hint'] ?? 'Configure your host mapping after you save the site.' }}</p>
            <div class="mt-3 text-xs uppercase tracking-[0.2em] text-slate-500">
                {{ $preview['deploy_path'] ?? '' }}
                @if (filled($preview['web_root'] ?? null))
                    / {{ $preview['web_root'] }}
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Configuration steps</div>
            <div class="mt-3 space-y-2">
                @forelse ($preview['config_steps'] ?? [] as $step)
                    <div class="flex items-start gap-2 text-sm text-slate-300">
                        <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                        <span>{{ $step }}</span>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">No preview steps available yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if (filled($hostnames))
        <div class="mt-5 rounded-2xl border border-white/5 bg-black/20 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Mapped hosts</div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($hostnames as $hostname)
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                        {{ $hostname }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
