<div class="sticky top-4 z-20 grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.9fr)]">
    <div class="rounded-2xl border border-white/10 bg-slate-950/90 p-4 backdrop-blur">
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

    <div class="rounded-2xl border border-white/10 bg-slate-950/90 p-4 backdrop-blur">
        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Suggested action</div>
        <div class="mt-2 text-sm font-semibold text-white">
            {{ $record->page_snapshot['next_action'] }}
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-400">
            {{ $record->page_snapshot['next_action_description'] }}
        </p>
    </div>
</div>

<div class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Quick checklist</div>
            <div class="mt-1 text-sm font-semibold text-white">{{ $record->page_snapshot['headline'] }}</div>
        </div>
        <div class="text-xs text-slate-400">
            {{ $record->steps()->count() }} step{{ $record->steps()->count() === 1 ? '' : 's' }} tracked
        </div>
    </div>

    <ul class="mt-4 grid gap-2 md:grid-cols-3">
        @foreach ($record->page_snapshot['checklist'] as $item)
            <li class="flex items-start gap-3 rounded-xl border border-white/5 bg-slate-950/60 px-3 py-3 text-sm leading-6 text-slate-300">
                <span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-[11px] font-bold text-emerald-300">{{ $loop->iteration }}</span>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
</div>
