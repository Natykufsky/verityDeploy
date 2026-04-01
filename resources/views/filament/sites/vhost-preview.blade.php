@php
    $preview = $preview ?? [];
    $hostnames = array_values(array_filter($preview['hostnames'] ?? []));
@endphp

<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full {{ ($preview['supported'] ?? false) ? 'bg-cyan-300' : 'bg-slate-400' }}"></span>
                vhost preview
            </div>
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ ($preview['engine_label'] ?? 'Vhost') . ' config' }}
            </h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-300">
                {{ $preview['message'] ?? 'Preview the vhost layout for this site.' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['engine'] ?? 'nginx' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['ssl_state'] ?? 'unconfigured' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['force_https'] ? 'https enforced' : 'http allowed' }}
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Document root</div>
            <div class="mt-2 break-all text-sm font-semibold text-white">{{ $preview['document_root'] ?? 'not set' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">SSL state</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['ssl_summary'] ?? 'ssl has not been configured yet.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">HTTPS redirect</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['force_https_summary'] ?? 'https redirects are disabled.' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Hostnames</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ count($hostnames) }} mapped host{{ count($hostnames) === 1 ? '' : 's' }}</div>
        </div>
    </div>

    @if (! ($preview['supported'] ?? false))
        <div class="mt-5 rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">
            {{ $preview['message'] ?? 'Enable vhost management on the server to preview the vhost config.' }}
        </div>
    @else
        <div class="mt-5 rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Preview snippet</div>
            <div class="mt-3 max-h-[320px] overflow-y-auto rounded-xl border border-white/5 bg-black/30 p-3">
                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ $preview['snippet'] ?? 'No vhost snippet available yet.' }}</pre>
            </div>
        </div>
    @endif

    <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Configuration steps</div>
            <div class="mt-3 space-y-2">
                @forelse ($preview['steps'] ?? [] as $step)
                    <div class="flex items-start gap-2 text-sm text-slate-300">
                        <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                        <span>{{ $step }}</span>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">No vhost steps available yet.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Mapped hosts</div>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($hostnames as $hostname)
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                        {{ $hostname }}
                    </span>
                @empty
                    <span class="text-sm text-slate-400">no hostnames mapped yet.</span>
                @endforelse
            </div>
        </div>
    </div>
</div>
