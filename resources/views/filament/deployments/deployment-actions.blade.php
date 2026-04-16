<div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.9fr)]">
    <div class="deployment-frost-card rounded-2xl p-4">
        <div class="flex flex-wrap items-center gap-2">
            @if ($record->status === 'running')
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">
                    <span class="h-2 w-2 rounded-full bg-amber-300 animate-pulse"></span>
                    Live update
                </span>
            @endif

            @if ($record->isResumable())
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">
                    Resume available
                </span>
            @endif

            @if ($record->status === 'failed')
                <span class="inline-flex items-center gap-2 rounded-full border border-rose-400/20 bg-rose-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-rose-300">
                    Needs attention
                </span>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="?tab=Progress#deployment-steps" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-emerald-400/30 hover:bg-emerald-500/10">
                Open progress
            </a>
            <a href="?tab=Commands#command-guide" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-emerald-400/30 hover:bg-emerald-500/10">
                Open command guide
            </a>
            <a href="?tab=Logs#deployment-terminal" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-emerald-400/30 hover:bg-emerald-500/10">
                Open logs
            </a>
        </div>
    </div>

    <div class="deployment-frost-card rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>Suggested action</span>
            <x-info-tooltip text="The most relevant next action for this deployment state." label="Suggested action help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">
            {{ $record->page_snapshot['next_action'] }}
        </div>
        <div class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-400">
            <p>{{ $record->page_snapshot['next_action_description'] }}</p>
            <x-info-tooltip text="Why that action is recommended right now." label="Suggested action description help" />
        </div>
    </div>
</div>


