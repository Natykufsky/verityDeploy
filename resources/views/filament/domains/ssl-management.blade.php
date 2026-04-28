@php
    $preview = $preview ?? [];
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">
            <span>SSL overview</span>
        </div>
        <div class="mt-3 space-y-2 text-sm text-slate-300">
            <div>Status: <span class="font-semibold text-white">{{ $preview['ssl_status'] ?? 'unconfigured' }}</span></div>
            <div>Renewal: <span class="font-semibold text-white">{{ $preview['renewal_status'] ?? 'unknown' }}</span></div>
            <div>Expires: <span class="font-semibold text-white">{{ $preview['ssl_expires_at'] ?? 'not set' }}</span></div>
            <div>Days remaining: <span class="font-semibold text-white">{{ $preview['days_remaining'] ?? 'unknown' }}</span></div>
        </div>
    </div>

    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">
            <span>Manual tracking</span>
        </div>
        <div class="mt-3 space-y-2 text-sm text-slate-300">
            <div>Certificate file: <span class="font-semibold text-white">{{ ($preview['certificate_present'] ?? false) ? 'stored' : 'missing' }}</span></div>
            <div>Private key: <span class="font-semibold text-white">{{ ($preview['key_present'] ?? false) ? 'stored' : 'missing' }}</span></div>
            <div>Chain bundle: <span class="font-semibold text-white">{{ ($preview['chain_present'] ?? false) ? 'stored' : 'missing' }}</span></div>
        </div>
    </div>
</div>

<div class="deployment-frost-panel mt-4 rounded-2xl p-4">
    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
        <span>SSL tracking steps</span>
    </div>
    <div class="mt-3 space-y-2">
        @foreach(($preview['steps'] ?? []) as $step)
            <div class="flex items-start gap-2 text-sm text-slate-300">
                <span class="mt-1 h-2 w-2 rounded-full bg-emerald-300"></span>
                <span>{{ $step }}</span>
            </div>
        @endforeach
    </div>
</div>
