@php
    $preview = $record->domain_preview;
    $checklist = $record->cpanel_deploy_checklist;
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            cPanel domain provisioning
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $record->primary_domain ?? 'no primary domain configured yet' }}
            </h3>
            <x-info-tooltip text="This action creates the cPanel addon domain and then provisions any matching subdomains and aliases." label="cPanel provisioning help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This action creates the addon domain in cPanel, then provisions any matching subdomains and parked alias domains that belong to this site.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>What will be created</span>
                <x-info-tooltip text="These are the pieces the provisioning action will create or update." label="What will be created help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>primary domain: <span class="font-semibold text-white">{{ $record->primary_domain ?? 'n/a' }}</span></div>
                <div>subdomains: <span class="font-semibold text-white">{{ count($preview['subdomains'] ?? []) }}</span></div>
                <div>alias domains: <span class="font-semibold text-white">{{ count($preview['alias_domains'] ?? []) }}</span></div>
                <div>document root: <span class="font-semibold text-white">{{ $preview['target_summary'] ?? 'n/a' }}</span></div>
                <div>ssl state: <span class="font-semibold text-white">{{ $record->ssl_summary }}</span></div>
                <div>https redirect: <span class="font-semibold text-white">{{ $record->force_https_summary }}</span></div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Preflight checklist</span>
                <x-info-tooltip text="The checklist helps confirm the server and site are ready before provisioning runs." label="Preflight checklist help" />
            </div>
            <div class="mt-3 space-y-2">
                @foreach ($checklist as $item)
                    <div class="flex items-start gap-2 text-sm text-slate-300">
                        <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                        <span>{{ $item }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>Preview</span>
            <x-info-tooltip text="The preview summarizes how cPanel will apply the new domain layout." label="Preview help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            The cPanel API will create the addon domain first, then add matching subdomains and parked alias domains when they are present.
        </p>
    </div>
</div>
