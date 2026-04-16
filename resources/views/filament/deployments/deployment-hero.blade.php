<div class="deployment-frost-card rounded-3xl p-5">
    @php($snapshot = $record->page_snapshot)

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-4">
            <div class="space-y-2">
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">
                        Deploying {{ $record->site->name }}
                    </h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ match ($record->status) {
                        'successful' => 'bg-emerald-500/15 text-emerald-300',
                        'running' => 'bg-amber-500/15 text-amber-300',
                        'failed' => 'bg-rose-500/15 text-rose-300',
                        'pending' => 'bg-sky-500/15 text-sky-300',
                        default => 'bg-white/5 text-slate-300',
                    } }}">
                        {{ ucfirst($record->status) }}
                    </span>
                </div>
                <div class="flex items-center gap-4 text-sm text-slate-300">
                    <span>Branch: <strong>{{ $record->branch }}</strong></span>
                    <span>Commit: <code class="bg-slate-700 px-1 rounded">{{ substr($record->commit_hash, 0, 7) }}</code></span>
                    <span>Triggered by {{ $record->triggeredBy->name ?? 'System' }}</span>
                </div>
            </div>

            <div class="deployment-frost-panel rounded-2xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-white">Progress</span>
                    <span class="text-sm text-slate-300">{{ $snapshot['progress']['percentage'] }}%</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-white/10 mb-2">
                    <div class="h-full rounded-full bg-linear-to-r from-emerald-400 via-cyan-400 to-sky-400 transition-all duration-300" style="width: {{ $snapshot['progress']['percentage'] }}%"></div>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $snapshot['progress']['summary'] }}</span>
                    <span>{{ $snapshot['timing']['elapsed'] }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="text-xs text-slate-400 uppercase tracking-wider">Quick Actions</div>
            <div class="flex gap-2">
                @if ($record->status === 'failed' || $record->status === 'successful')
                    <a href="#" wire:click="queueDeployment('manual', '{{ $record->branch }}', '{{ $record->commit_hash }}')" class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-300 hover:bg-emerald-500/20">
                        Redeploy
                    </a>
                @endif
                @if ($record->isResumable())
                    <a href="#" wire:click="queueResume" class="inline-flex items-center gap-2 rounded-lg border border-blue-500/20 bg-blue-500/10 px-3 py-2 text-sm font-medium text-blue-300 hover:bg-blue-500/20">
                        Resume
                    </a>
                @endif
                @if (filled($this->getRollbackTarget()))
                    <a href="#" wire:click="queueRollback" class="inline-flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm font-medium text-amber-300 hover:bg-amber-500/20">
                        Rollback
                    </a>
                @endif
            </div>
        </div>
    </div>


</div>
