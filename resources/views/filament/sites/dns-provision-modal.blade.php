@php
    $preview = $record->dns_preview;
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            dns provisioning
        </div>
        <h3 class="mt-3 text-lg font-semibold tracking-tight text-white">
            {{ $record->primary_domain ?? 'no primary domain configured yet' }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This action creates or updates the Cloudflare DNS records for the site hostnames.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">What will be created</div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                @foreach ($preview['records'] ?? [] as $recordRow)
                    <div>{{ $recordRow['type'] }} {{ $recordRow['name'] }} → {{ $recordRow['content'] }}</div>
                @endforeach
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Preflight checklist</div>
            <div class="mt-3 space-y-2">
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>Cloudflare API token configured on the server.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>Cloudflare zone configured or discoverable by the primary domain.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>DNS capability enabled for this server.</span>
                </div>
            </div>
        </div>
    </div>
</div>
