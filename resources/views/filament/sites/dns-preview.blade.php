@php
    $preview = $preview ?? [];
    $records = $preview['records'] ?? [];
@endphp

<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full {{ ($preview['supported'] ?? false) ? 'bg-cyan-300' : 'bg-slate-400' }}"></span>
                dns preview
            </div>
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $preview['provider'] ?? 'Cloudflare' }}
            </h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-300">
                {{ $preview['message'] ?? 'Preview the DNS records that will be created or updated.' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ $preview['zone_id'] ?: 'zone lookup' }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                {{ count($records) }} records
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Primary domain</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['primary_domain'] ?? 'not set' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Proxy mode</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['proxy'] ? 'proxied' : 'dns only' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Record count</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ count($records) }} total</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Status</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ ($preview['supported'] ?? false) ? 'ready' : 'needs setup' }}</div>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-white/5 bg-black/30 p-4">
        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Planned records</div>
        <div class="mt-3 space-y-2">
            @forelse ($records as $record)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/5 bg-white/5 px-4 py-3 text-sm text-slate-200">
                    <div class="font-semibold">{{ $record['name'] }}</div>
                    <div class="font-mono text-xs uppercase tracking-[0.2em] text-slate-400">{{ $record['type'] }} → {{ $record['content'] }}</div>
                </div>
            @empty
                <div class="text-sm text-slate-400">no dns records are planned yet.</div>
            @endforelse
        </div>
    </div>

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
                    <div class="text-sm text-slate-400">No dns steps available yet.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-white/5 bg-black/30 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Notes</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>Cloudflare records are created or updated in place.</div>
                <div>Proxy mode follows the server DNS settings.</div>
                <div>The origin target defaults to the server IP address.</div>
            </div>
        </div>
    </div>
</div>
