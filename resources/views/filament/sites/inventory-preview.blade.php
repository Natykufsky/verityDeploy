@php
    $preview = $preview ?? [];
    $snapshot = $preview['snapshot'] ?? [];
    $domains = $snapshot['domains'] ?? [];
    $dns = $snapshot['dns'] ?? [];
    $ssl = $snapshot['ssl'] ?? [];
    $notes = $snapshot['notes'] ?? [];
    $syncState = filled($snapshot) ? 'synced' : 'not synced';
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">live inventory</p>
                    <x-info-tooltip text="This snapshot reflects the live cPanel domain, DNS, and SSL state stored on the site record." label="Live inventory help" />
                </div>
                <h3 class="text-lg font-semibold tracking-tight text-white">cPanel configuration snapshot</h3>
                <p class="text-sm leading-6 text-slate-300">
                    {{ $preview['message'] ?? 'No live inventory has been synced yet.' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $preview['source'] ?? 'cPanel' }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $syncState }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $preview['synced_at'] ?? 'never synced' }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Domains</span>
                <x-info-tooltip text="Primary and addon domains currently known to the cPanel account." label="Domains help" />
            </div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ count($domains['addon_domains'] ?? []) + (filled($domains['main'] ?? null) ? 1 : 0) }}</p>
            <p class="mt-1 text-xs text-slate-400">primary and addon domains</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Subdomains</span>
                <x-info-tooltip text="Subdomains currently present in the cPanel inventory." label="Subdomains help" />
            </div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ count($domains['subdomains'] ?? []) }}</p>
            <p class="mt-1 text-xs text-slate-400">mapped subdomain records</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>DNS</span>
                <x-info-tooltip text="DNS zone records fetched from cPanel for the primary domain." label="DNS help" />
            </div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ count($dns['records'] ?? []) }}</p>
            <p class="mt-1 text-xs text-slate-400">zone records captured</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>SSL hosts</span>
                <x-info-tooltip text="Installed SSL hosts returned by cPanel for the account." label="SSL help" />
            </div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ count($ssl['hosts'] ?? []) }}</p>
            <p class="mt-1 text-xs text-slate-400">hosts with ssl metadata</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <details class="deployment-frost-panel rounded-2xl p-4 xl:col-span-1" open>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <span>Domain list</span>
                    <x-info-tooltip text="The live cPanel domain list normalized from the account inventory." label="Domain list help" />
                </div>
                <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">open</span>
            </summary>
            <div class="mt-4 space-y-3">
                @if (filled($domains['main'] ?? null))
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">main</div>
                        <div class="mt-1 text-sm font-semibold text-white">{{ $domains['main']['domain'] ?? 'n/a' }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ $domains['main']['document_root'] ?? 'document root unavailable' }}</div>
                    </div>
                @endif

                @forelse ($domains['addon_domains'] ?? [] as $domain)
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="text-sm font-semibold text-white">{{ $domain['domain'] ?? 'n/a' }}</div>
                        <div class="mt-1 text-xs text-slate-400">
                            {{ $domain['root_domain'] ?? 'no root domain' }}
                            @if (filled($domain['document_root'] ?? null))
                                · {{ $domain['document_root'] }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3 text-sm text-slate-400">
                        No addon domains were returned by cPanel.
                    </div>
                @endforelse
            </div>
        </details>

        <details class="deployment-frost-panel rounded-2xl p-4 xl:col-span-1">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <span>DNS records</span>
                    <x-info-tooltip text="The fetched DNS records for the site primary domain." label="DNS records help" />
                </div>
                <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">open</span>
            </summary>
            <div class="mt-4 space-y-3">
                @forelse ($dns['records'] ?? [] as $record)
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-300">{{ $record['type'] ?? 'record' }}</span>
                            <span class="text-sm font-semibold text-white">{{ $record['name'] ?? 'n/a' }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-400">{{ $record['content'] ?? 'no content' }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3 text-sm text-slate-400">
                        No DNS records were returned.
                    </div>
                @endforelse
            </div>
        </details>

        <details class="deployment-frost-panel rounded-2xl p-4 xl:col-span-1">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <span>SSL hosts</span>
                    <x-info-tooltip text="The SSL inventory currently installed in cPanel for this account." label="SSL hosts help" />
                </div>
                <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">open</span>
            </summary>
            <div class="mt-4 space-y-3">
                @forelse ($ssl['hosts'] ?? [] as $host)
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="text-sm font-semibold text-white">{{ $host['domain'] ?? 'n/a' }}</div>
                        <div class="mt-1 text-xs text-slate-400">
                            {{ $host['issuer'] ?? 'issuer unavailable' }}
                            @if (filled($host['not_after'] ?? null))
                                · expires {{ $host['not_after'] }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3 text-sm text-slate-400">
                        No SSL hosts were returned.
                    </div>
                @endforelse
            </div>
        </details>
    </div>

    @if (filled($notes))
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Notes</span>
                <x-info-tooltip text="Short inventory notes from the sync process." label="Notes help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                @foreach ($notes as $note)
                    <div>{{ $note }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if (filled($preview['last_error'] ?? null) && ($preview['last_error'] ?? '') !== 'No sync errors recorded.')
        <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            <div class="font-semibold uppercase tracking-[0.24em] text-rose-200">Last sync error</div>
            <div class="mt-2 leading-6">{{ $preview['last_error'] }}</div>
        </div>
    @endif
</div>
