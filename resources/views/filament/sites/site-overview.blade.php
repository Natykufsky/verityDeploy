<div class="deployment-frost-card rounded-3xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-4">
            <div class="space-y-2">
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">{{ $record->name }}</h2>
                    @if($record->domains->isNotEmpty())
                        <span class="text-lg text-slate-300">{{ $record->domains->first()->domain }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-4 text-sm text-slate-300">
                    <span>Server: {{ $record->server?->name ?? 'None' }}</span>
                    <span>PHP: {{ $record->php_version ?? 'Not set' }}</span>
                    <span>Status: {{ $record->active ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>

            <div class="deployment-frost-panel rounded-2xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-white">Last Deployed</span>
                    <span class="text-sm text-slate-300">{{ $record->last_deployed_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
                <div class="text-sm text-slate-400">
                    {{ $record->last_successful_deploy_badge ?? 'No successful deployments' }}
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="text-xs text-slate-400 uppercase tracking-wider">Quick Actions</div>
            <div class="flex gap-2">
                @if($record->active)
                    <a href="#" wire:click="triggerDeploy('manual', '{{ $record->default_branch }}')" class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-300 hover:bg-emerald-500/20">
                        Deploy Now
                    </a>
                @endif
                <a href="?tab=History" class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10">
                    View History
                </a>
            </div>
        </div>
    </div>

</div>
