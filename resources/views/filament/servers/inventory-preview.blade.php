@php
    $preview = $preview ?? [];
    $domains = collect($domains ?? []);
    $typeCounts = [
        'primary' => $domains->where('type', 'primary')->count(),
        'addon' => $domains->where('type', 'addon')->count(),
        'subdomain' => $domains->where('type', 'subdomain')->count(),
        'alias' => $domains->where('type', 'alias')->count(),
    ];
    $sslEnabledCount = $domains->where('is_ssl_enabled', true)->count();
    $activeCount = $domains->where('is_active', true)->count();
    $siteCount = $domains
        ->pluck('site_id')
        ->filter()
        ->unique()
        ->count();
    $supported = (bool) ($preview['supported'] ?? false);
@endphp

<div
    class="space-y-4"
    x-data="{ search: '' }"
>
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">server inventory</p>
                    <x-info-tooltip text="This view shows the live cPanel domain inventory attached to the server, including site links and SSL state." label="Server inventory help" />
                </div>
                <h3 class="text-lg font-semibold tracking-tight text-white">
                    {{ $record->name }} domain inventory
                </h3>
                <p class="text-sm leading-6 text-slate-300">
                    {{ $preview['message'] ?? 'Live domain records managed on the server side.' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $domains->count() }} domains
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $siteCount }} sites
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $sslEnabledCount }} ssl enabled
                </span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-200">
                    {{ $supported ? 'live cPanel sync' : 'local fallback' }}
                </span>
            </div>
        </div>
    </div>

    @if (filled($preview['synced_at'] ?? null))
        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs uppercase tracking-[0.2em] text-slate-300">
            Synced at {{ $preview['synced_at'] }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Primary</div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $typeCounts['primary'] }}</p>
            <p class="mt-1 text-xs text-slate-400">main domains</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Addon</div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $typeCounts['addon'] }}</p>
            <p class="mt-1 text-xs text-slate-400">addon domains</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Subdomains</div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $typeCounts['subdomain'] }}</p>
            <p class="mt-1 text-xs text-slate-400">subdomain records</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Active</div>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $activeCount }}</p>
            <p class="mt-1 text-xs text-slate-400">enabled domains</p>
        </div>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4 flex h-[38rem] flex-col overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <div class="flex items-center gap-2">
                <span>Domain records</span>
                <x-info-tooltip text="Each row shows the domain name, directory, site, SSL state, and quick management actions." label="Domain records help" />
            </div>
            <div class="w-full sm:w-80">
                <label class="sr-only" for="server-domain-search">Search domains</label>
                <input
                    id="server-domain-search"
                    x-model.debounce.200ms="search"
                    type="search"
                    placeholder="Search domain, site, or docroot"
                    class="w-full rounded-full border border-white/10 bg-black/30 px-4 py-2 text-sm font-medium text-white placeholder:text-slate-500 focus:border-emerald-500/40 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                />
            </div>
        </div>

        <div class="mt-4 min-h-0 flex-1 overflow-y-auto pr-1">
            <div class="grid gap-3">
                @forelse ($domains as $domain)
                    @php
                        $domainId = $domain['id'] ?? null;
                        $domainEditUrl = filled($domainId) ? \App\Filament\Resources\Domains\DomainResource::getUrl('edit', ['record' => $domainId]) : null;
                        $searchHaystack = strtolower(trim(implode(' ', array_filter([
                            (string) ($domain['name'] ?? ''),
                            (string) ($domain['site_name'] ?? ''),
                            (string) ($domain['document_root'] ?? ''),
                            (string) ($domain['type'] ?? ''),
                        ]))));
                    @endphp
                    <div
                        class="rounded-2xl border border-white/5 bg-black/20 p-4"
                        x-show="search === '' || {{ json_encode($searchHaystack) }}.includes(search.toLowerCase())"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="text-base font-semibold text-white">{{ $domain['name'] }}</div>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                                        {{ $domain['type'] ?? 'domain' }}
                                    </span>
                                    @if (filled($domain['site_id'] ?? null))
                                        <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-300">
                                            linked to site
                                        </span>
                                    @else
                                        <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                                            unlinked
                                        </span>
                                    @endif
                                    @if ($domain['is_active'] ?? false)
                                        <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-300">
                                            active
                                        </span>
                                    @else
                                        <span class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                                            inactive
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-3 text-sm text-slate-400">
                                    <span>{{ $domain['site_name'] ?? 'Unassigned site' }}</span>
                                    <span>cPanel docroot: <span class="text-slate-200">{{ filled($domain['document_root'] ?? null) ? $domain['document_root'] : 'n/a' }}</span></span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if (filled($domain['live_url'] ?? null))
                                    <a
                                        href="{{ $domain['live_url'] }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-300 transition hover:bg-emerald-500/20"
                                    >
                                        Open live site
                                    </a>
                                @endif
                                @if (filled($domainEditUrl))
                                    <a
                                        href="{{ $domainEditUrl }}"
                                        class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-300 transition hover:bg-amber-500/20"
                                    >
                                        Edit cPanel docroot
                                    </a>
                                @endif
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200">
                                    {{ $domain['is_ssl_enabled'] ?? false ? 'SSL enabled' : 'SSL off' }}
                                </span>
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200">
                                    {{ $domain['ssl_status'] ?: 'no ssl status' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Site</div>
                                <div class="mt-1 font-medium text-white">{{ $domain['site_name'] ?? 'None' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">cPanel docroot</div>
                                <div class="mt-1 font-medium text-white break-all">
                                    {{ filled($domain['document_root'] ?? null) ? $domain['document_root'] : 'n/a' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">SSL expires</div>
                                <div class="mt-1 font-medium text-white">
                                    {{ filled($domain['ssl_expires_at'] ?? null) ? \Illuminate\Support\Carbon::parse($domain['ssl_expires_at'])->format('M d, Y H:i') : 'n/a' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">External ID</div>
                                <div class="mt-1 font-medium text-white">{{ $domain['external_id'] ?: 'n/a' }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                        No domain records have been attached to this server yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
