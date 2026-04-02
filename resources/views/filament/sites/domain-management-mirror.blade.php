@php
    $preview = $preview ?? [];
    $subdomains = (array) ($preview['subdomains'] ?? []);
    $aliasDomains = (array) ($preview['alias_domains'] ?? []);
    $primaryDomain = $preview['primary_domain'] ?? null;
    $sslStatus = $preview['ssl_state'] ?? 'unconfigured';
    $forceHttps = (bool) ($preview['force_https'] ?? false);
    $deployPath = $preview['deploy_path'] ?? 'not set';
    $webRoot = $preview['web_root'] ?? 'public';
@endphp

<div class="deployment-frost-card rounded-3xl p-0 overflow-hidden border border-white/10 bg-white/5">
    <div class="p-5 border-b border-white/10 bg-white/5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-500/10 rounded-lg">
                <x-filament::icon icon="heroicon-m-globe-alt" class="w-5 h-5 text-emerald-400" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-white tracking-tight">Domain Management Mirror</h3>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold mt-0.5">Live Server Configuration Shadow</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
             <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-300">
                {{ count($subdomains) + count($aliasDomains) + ($primaryDomain ? 1 : 0) }} total hosts
            </span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-black/20 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    <th class="px-5 py-3 border-b border-white/5">Domain</th>
                    <th class="px-5 py-3 border-b border-white/5">Document Root</th>
                    <th class="px-5 py-3 border-b border-white/5">Redirection</th>
                    <th class="px-5 py-3 border-b border-white/5">Security</th>
                    <th class="px-5 py-3 border-b border-white/5 text-right">Context</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @if($primaryDomain)
                    <tr class="group hover:bg-white/5 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]"></div>
                                <div>
                                    <span class="text-sm font-bold text-white">{{ $primaryDomain }}</span>
                                    <span class="block text-[10px] text-slate-500 font-medium uppercase tracking-tight">Primary Service Domain</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <code class="text-[11px] text-cyan-300 bg-cyan-500/5 px-2 py-1 rounded border border-cyan-500/10">
                                {{ $deployPath }}/{{ $webRoot }}
                            </code>
                        </td>
                        <td class="px-5 py-4">
                            @if($forceHttps)
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-400">
                                    <x-filament::icon icon="heroicon-m-arrow-path" class="w-3.5 h-3.5" />
                                    HTTPS Force
                                </span>
                            @else
                                <span class="text-[11px] font-bold text-slate-500 italic">None</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                             <span class="inline-flex items-center rounded-full border border-white/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ in_array($sslStatus, ['valid', 'issued', 'active']) ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : 'bg-amber-500/10 text-amber-300 border-amber-500/20' }}">
                                {{ $sslStatus }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                             <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-slate-600 inline-block" />
                        </td>
                    </tr>
                @endif

                @foreach($subdomains as $sub)
                    <tr class="group hover:bg-white/5 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-cyan-400"></div>
                                <div>
                                    <span class="text-sm font-semibold text-slate-200">{{ $sub }}</span>
                                    <span class="block text-[10px] text-slate-500 font-medium uppercase tracking-tight">Addon / Subdomain</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <code class="text-[11px] text-slate-400">
                                {{ $deployPath }}/{{ $webRoot }}
                            </code>
                        </td>
                        <td class="px-5 py-4">
                             <span class="text-[11px] font-bold text-slate-500 italic">Inherited</span>
                        </td>
                        <td class="px-5 py-4">
                             <span class="text-[11px] font-bold text-slate-500">AutoSSL</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                             <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-slate-700 inline-block" />
                        </td>
                    </tr>
                @endforeach

                @foreach($aliasDomains as $alias)
                    <tr class="group hover:bg-white/5 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                                <div>
                                    <span class="text-sm font-semibold text-slate-200">{{ $alias }}</span>
                                    <span class="block text-[10px] text-slate-500 font-medium uppercase tracking-tight">Parked / Alias</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <code class="text-[11px] text-slate-400 italic">
                                aliased to primary
                            </code>
                        </td>
                        <td class="px-5 py-4">
                             <span class="text-[11px] font-bold text-slate-500 italic">None</span>
                        </td>
                        <td class="px-5 py-4">
                             <span class="text-[11px] font-bold text-slate-500">Shared</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                             <x-filament::icon icon="heroicon-m-chevron-right" class="w-4 h-4 text-slate-700 inline-block" />
                        </td>
                    </tr>
                @endforeach
                
                @if(!$primaryDomain && count($subdomains) === 0 && count($aliasDomains) === 0)
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm italic">
                            No domains configured for this site yet. Set a primary domain in the editor to sync with the server.
                        </td>
                    </tr>
                @endif
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
                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Alias
            </div>
        </div>
        <div class="text-slate-500 italic">
            Synchronized with <span class="text-slate-300 font-bold uppercase">{{ $preview['connection_type'] ?? 'remote server' }}</span>
        </div>
    </div>
</div>
