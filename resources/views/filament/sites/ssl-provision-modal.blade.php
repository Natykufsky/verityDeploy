@php
    $preview = $record->ssl_preview;
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            ssl provisioning
        </div>
        <h3 class="mt-3 text-lg font-semibold tracking-tight text-white">
            {{ $record->primary_domain ?? 'no primary domain configured yet' }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This generates a cPanel SSL certificate for the site primary domain and marks the site as ssl ready.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">What will change</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>ssl state: <span class="font-semibold text-white">{{ $preview['ssl_state'] ?? 'unconfigured' }}</span></div>
                <div>force https: <span class="font-semibold text-white">{{ $preview['force_https'] ? 'enabled' : 'disabled' }}</span></div>
                <div>last synced: <span class="font-semibold text-white">{{ $preview['ssl_last_synced_at'] ?? 'never synced' }}</span></div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Preflight checklist</div>
            <div class="mt-3 space-y-2">
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-emerald-300"></span>
                    <span>SSL capability enabled on the server.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-emerald-300"></span>
                    <span>Primary domain configured for the site.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-emerald-300"></span>
                    <span>Domain provisioning already planned or complete.</span>
                </div>
            </div>
        </div>
    </div>
</div>
