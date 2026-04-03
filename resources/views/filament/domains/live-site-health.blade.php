@php
    $preview = $preview ?? [];
    $status = (string) ($preview['status'] ?? 'unknown');
    $statusLabel = $preview['status_label'] ?? ucfirst($status);
    $statusTone = match ($status) {
        'healthy' => 'success',
        'redirect' => 'info',
        'forbidden', 'unauthorized', 'server-error' => 'danger',
        'not-found', 'unreachable', 'unexpected' => 'warning',
        default => 'gray',
    };
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">live site health</p>
                    <x-info-tooltip text="This probes the public domain and translates common HTTP states into plain language." label="Live site help" />
                </div>
                <h3 class="text-lg font-semibold tracking-tight text-white">Live response check</h3>
                <p class="text-sm leading-6 text-slate-300">
                    {{ $preview['message'] ?? 'Checking the live site response.' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $statusLabel }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $preview['status_code'] ?? 'n/a' }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $preview['checked_at'] ?? 'just now' }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">URL</div>
            <div class="mt-2 text-sm font-semibold text-white break-all">{{ $preview['url'] ?? 'not available' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Status</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $statusLabel }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Server</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['server_header'] ?? 'n/a' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Mode</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $preview['supported'] ? 'available' : 'unavailable' }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-{{ $statusTone === 'danger' ? 'rose' : ($statusTone === 'success' ? 'emerald' : ($statusTone === 'info' ? 'sky' : 'amber')) }}-500/20 bg-{{ $statusTone === 'danger' ? 'rose' : ($statusTone === 'success' ? 'emerald' : ($statusTone === 'info' ? 'sky' : 'amber')) }}-500/10 p-4 text-sm text-white">
        <div class="font-semibold uppercase tracking-[0.24em] text-white/80">
            {{ $statusLabel === 'Forbidden' ? 'Likely cause' : 'What this means' }}
        </div>
        <div class="mt-2 leading-6">{{ $preview['hint'] ?? 'No hint available.' }}</div>
        @if (filled($preview['error'] ?? null))
            <div class="mt-3 rounded-xl border border-white/10 bg-black/20 p-3 text-xs text-white/80 break-all">
                {{ $preview['error'] }}
            </div>
        @endif
    </div>
</div>
