@php
    $preview = $record->live_configuration_preview;
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            live inventory sync
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $record->primary_domain ?? 'no primary domain configured yet' }}
            </h3>
            <x-info-tooltip text="This fetches the current cPanel inventory and stores it on the site record without changing the remote server." label="Inventory sync help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This reads the live domain, DNS, and SSL inventory from cPanel so you can compare it against the site intent and manage existing configurations more easily.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>What will be fetched</span>
                <x-info-tooltip text="These are the live inventory sections that will be pulled from cPanel." label="What will be fetched help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>domains: <span class="font-semibold text-white">primary, addon, subdomain, and parked domains</span></div>
                <div>dns: <span class="font-semibold text-white">zone records for the primary domain</span></div>
                <div>ssl: <span class="font-semibold text-white">installed SSL host metadata</span></div>
                <div>last synced: <span class="font-semibold text-white">{{ $preview['synced_at'] ?? 'never synced' }}</span></div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Preflight checklist</span>
                <x-info-tooltip text="The checklist confirms the cPanel inventory prerequisites before syncing runs." label="Preflight checklist help" />
            </div>
            <div class="mt-3 space-y-2">
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>cPanel API token configured on the server.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>Primary domain configured when DNS inventory is needed.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>Read-only sync only updates the normalized snapshot on the site.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>Preview</span>
            <x-info-tooltip text="The preview summarizes the live inventory snapshot that will be refreshed." label="Preview help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This does not change the remote server. It only reads the live cPanel inventory and stores the snapshot on the site record for display and drift checks.
        </p>
    </div>
</div>
