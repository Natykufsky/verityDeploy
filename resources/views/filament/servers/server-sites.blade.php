<div class="space-y-4">
    @php
        $sites = $record->sites()->with(['primaryDomain', 'domains'])->get();
    @endphp

    @forelse ($sites as $site)
        <div class="deployment-frost-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-white">{{ $site->name }}</h3>
                        <span class="inline-flex items-center rounded-full border border-white/10 px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $site->active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-gray-500/15 text-gray-300' }}">
                            {{ $site->active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-slate-400">
                        <span>Domain: {{ $site->primaryDomain?->name ?? 'None' }}</span>
                        <span>PHP: {{ $site->php_version ?? 'N/A' }}</span>
                        <span>Last Deploy: {{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('filament.admin.resources.sites.view', $site) }}" class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10">
                        View Site
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-center text-slate-400">
            No sites deployed on this server yet.
            <a href="{{ route('filament.admin.resources.sites.create') }}" class="text-cyan-400 hover:text-cyan-300">Create your first site</a>.
        </div>
    @endforelse
</div>