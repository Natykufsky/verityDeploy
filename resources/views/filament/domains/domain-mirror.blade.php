@php
    $preview = $preview ?? [];
    $record = $record ?? null;
    $site = $record?->site;
    $siteName = $site?->name ?? 'Linked site';

    $normalizeDomain = function (mixed $item, string $type): ?array {
        if (is_string($item)) {
            $domain = trim($item);

            return $domain === '' ? null : [
                'domain' => $domain,
                'type' => $type,
                'document_root' => null,
                'root_domain' => null,
                'https_redirect' => null,
                'raw' => null,
            ];
        }

        if (! is_array($item)) {
            return null;
        }

        $domain = data_get($item, 'domain')
            ?? data_get($item, 'domain_name')
            ?? data_get($item, 'name')
            ?? data_get($item, 'fullsubdomain')
            ?? data_get($item, 'subdomain')
            ?? data_get($item, 'main_domain');

        if (! filled($domain)) {
            return null;
        }

        return [
            'domain' => (string) $domain,
            'type' => $type,
            'document_root' => data_get($item, 'document_root')
                ?? data_get($item, 'documentroot')
                ?? data_get($item, 'dir')
                ?? data_get($item, 'path'),
            'root_domain' => data_get($item, 'root_domain')
                ?? data_get($item, 'rootdomain')
                ?? data_get($item, 'parent_domain')
                ?? data_get($item, 'topdomain'),
            'https_redirect' => data_get($item, 'https_redirect')
                ?? data_get($item, 'redirects_to_https')
                ?? data_get($item, 'force_https_redirect'),
            'raw' => $item,
        ];
    };

    $rows = collect();

    $mainDomain = data_get($preview, 'domains.main')
        ?? data_get($preview, 'primary_domain');
    if ($row = $normalizeDomain($mainDomain, 'primary')) {
        $rows->push($row);
    }

    foreach ((array) data_get($preview, 'domains.addon_domains', []) as $item) {
        if ($row = $normalizeDomain($item, 'addon')) {
            $rows->push($row);
        }
    }

    foreach ((array) data_get($preview, 'domains.subdomains', []) as $item) {
        if ($row = $normalizeDomain($item, 'subdomain')) {
            $rows->push($row);
        }
    }

    foreach ((array) data_get($preview, 'domains.parked_domains', []) as $item) {
        if ($row = $normalizeDomain($item, 'alias')) {
            $rows->push($row);
        }
    }

    $rows = $rows
        ->filter(fn (array $row): bool => filled($row['domain'] ?? null))
        ->unique(fn (array $row): string => strtolower(trim((string) $row['domain'])))
        ->values();

    $total = $rows->count();
    $primaryCount = $rows->where('type', 'primary')->count();
    $addonCount = $rows->where('type', 'addon')->count();
    $subdomainCount = $rows->where('type', 'subdomain')->count();
    $aliasCount = $rows->where('type', 'alias')->count();
    $directDocroots = $rows->whereNotNull('document_root')->count();
@endphp

<div class="deployment-frost-card rounded-3xl p-0 overflow-hidden border border-white/10 bg-white/5">
    <div class="p-5 border-b border-white/10 bg-white/5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-500/10 rounded-lg">
                <x-filament::icon icon="heroicon-m-globe-alt" class="w-5 h-5 text-emerald-400" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-white tracking-tight">Domain mirror</h3>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold mt-0.5">{{ $siteName }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-300">
                {{ $total }} total hosts
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-300">
                {{ $directDocroots }} cPanel docroots
            </span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-black/20 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    <th class="px-5 py-3 border-b border-white/5">Domain</th>
                    <th class="px-5 py-3 border-b border-white/5">cPanel Docroot</th>
                    <th class="px-5 py-3 border-b border-white/5">Root Domain</th>
                    <th class="px-5 py-3 border-b border-white/5">Security</th>
                    <th class="px-5 py-3 border-b border-white/5 text-right">Context</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($rows as $row)
                    <tr class="group hover:bg-white/5 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full {{ match ($row['type']) {
                                    'primary' => 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]',
                                    'addon' => 'bg-cyan-400',
                                    'subdomain' => 'bg-sky-400',
                                    'alias' => 'bg-amber-400',
                                    default => 'bg-slate-400',
                                } }}"></div>
                                <div>
                                    <span class="text-sm font-bold text-white">{{ $row['domain'] }}</span>
                                    <span class="block text-[10px] text-slate-500 font-medium uppercase tracking-tight">
                                        {{ match ($row['type']) {
                                            'primary' => 'Primary service domain',
                                            'addon' => 'Addon domain',
                                            'subdomain' => 'Subdomain',
                                            'alias' => 'Parked / alias domain',
                                            default => 'Domain record',
                                        } }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            @if(filled($row['document_root'] ?? null))
                                <code class="text-[11px] text-cyan-300 bg-cyan-500/5 px-2 py-1 rounded border border-cyan-500/10 break-all">
                                    {{ $row['document_root'] }}
                                </code>
                            @else
                                <span class="text-[11px] font-bold text-slate-500 italic">n/a</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if(filled($row['root_domain'] ?? null))
                                <span class="text-[11px] font-semibold text-slate-200">{{ $row['root_domain'] }}</span>
                            @else
                                <span class="text-[11px] font-bold text-slate-500 italic">none</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if(($row['type'] ?? null) === 'alias')
                                <span class="text-[11px] font-bold text-slate-500">Shared</span>
                            @elseif(filled($row['https_redirect'] ?? null))
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-400">
                                    <x-filament::icon icon="heroicon-m-arrow-path" class="w-3.5 h-3.5" />
                                    HTTPS redirect
                                </span>
                            @else
                                <span class="text-[11px] font-bold text-slate-500 italic">None</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-300">
                                {{ ucfirst((string) $row['type']) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm italic">
                            No cPanel domain inventory is available for this site yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4 bg-black/40 border-t border-white/5 flex items-center justify-between text-[10px]">
        <div class="flex items-center gap-4 text-slate-400 font-medium uppercase tracking-widest">
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Primary
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> Addon
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-sky-400"></span> Subdomain
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Alias
            </div>
        </div>
        <div class="text-slate-500 italic">
            Live cPanel data for <span class="text-slate-300 font-bold uppercase">{{ $siteName }}</span>
        </div>
    </div>
</div>
