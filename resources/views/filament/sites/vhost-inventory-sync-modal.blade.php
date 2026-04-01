@php
    $preview = $record->live_configuration_preview;
    $expected = $preview['expected'] ?? [];
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            live vhost sync
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $record->primary_domain ?? 'no primary domain configured yet' }}
            </h3>
            <x-info-tooltip text="This fetches the current web server config and stores a normalized vhost snapshot on the site record." label="Vhost sync help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This inspects the remote web server config and stores a read-only snapshot so you can compare the live vhost layout against the site intent.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>What will be fetched</span>
                <x-info-tooltip text="These are the live config details the vhost inventory sync will capture." label="What will be fetched help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>engine: <span class="font-semibold text-white">{{ $preview['engine_label'] ?? 'nginx' }}</span></div>
                <div>hostnames: <span class="font-semibold text-white">{{ count($preview['expected']['hostnames'] ?? []) }}</span></div>
                <div>document root: <span class="font-semibold text-white">{{ $preview['expected']['document_root'] ?? 'n/a' }}</span></div>
                <div>last synced: <span class="font-semibold text-white">{{ $preview['synced_at'] ?? 'never synced' }}</span></div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Preflight checklist</span>
                <x-info-tooltip text="The checklist confirms the vhost inventory prerequisites before syncing runs." label="Preflight checklist help" />
            </div>
            <div class="mt-3 space-y-2">
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>SSH access configured on the server.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>Vhost management capability enabled on the server.</span>
                </div>
                <div class="flex items-start gap-2 text-sm text-slate-300">
                    <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                    <span>The sync is read-only and does not modify the remote config.</span>
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
            The stored snapshot will include the live vhost output, extracted hostnames, highlight lines, and the expected site layout for drift comparison.
        </p>
    </div>
</div>
