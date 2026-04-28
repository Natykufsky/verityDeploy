@php
    $runs = collect($record->recent_process_runs ?? [])
        ->sortByDesc(fn (array $run) => filled($run['started_at'] ?? null) ? \Illuminate\Support\Carbon::parse($run['started_at']) : \Illuminate\Support\Carbon::createFromTimestamp(0))
        ->values();
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>process history</span>
            <x-info-tooltip text="Recent process runs are stored in the site's terminal run history so you can review what was executed." label="Process history help" />
        </div>
        <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
            {{ $runs->count() }} runs
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($runs as $run)
            @php
                $startedAt = filled($run['started_at'] ?? null) ? \Illuminate\Support\Carbon::parse($run['started_at']) : null;
                $finishedAt = filled($run['finished_at'] ?? null) ? \Illuminate\Support\Carbon::parse($run['finished_at']) : null;
                $status = $run['status'] ?? 'queued';
            @endphp

            <div class="deployment-frost-card rounded-2xl border border-white/5 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-white">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $run['command'])) }}</span>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ $status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : ($status === 'running' ? 'bg-cyan-500/15 text-cyan-300' : ($status === 'failed' ? 'bg-rose-500/15 text-rose-300' : 'bg-slate-500/15 text-slate-300')) }}">
                        {{ $status }}
                    </span>
                    @if (filled($run['user_name'] ?? null))
                        <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                            {{ $run['user_name'] }}
                        </span>
                    @endif
                </div>

                <div class="mt-2 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">started</div>
                        <div class="mt-1 text-sm text-slate-100">{{ $startedAt?->format('M d, Y H:i:s') ?? 'n/a' }}</div>
                    </div>
                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">finished</div>
                        <div class="mt-1 text-sm text-slate-100">{{ $finishedAt?->format('M d, Y H:i:s') ?? 'n/a' }}</div>
                    </div>
                    <div class="md:col-span-2 rounded-xl bg-black/30 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">command</div>
                        <div class="mt-1 break-all font-mono text-xs text-slate-100">{{ $run['command'] ?? 'n/a' }}</div>
                    </div>
                    @if (filled($run['error_message'] ?? null))
                        <div class="md:col-span-2 rounded-xl border border-rose-500/20 bg-rose-950/30 p-3 text-sm text-rose-100">
                            <div class="text-[11px] uppercase tracking-wide text-rose-300/80">error</div>
                            <div class="mt-1">{{ $run['error_message'] }}</div>
                        </div>
                    @endif
                    @if (filled($run['output'] ?? null))
                        <div class="md:col-span-2 rounded-xl bg-black/30 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-slate-500">output</div>
                            <div class="mt-2 max-h-[220px] overflow-y-auto rounded-xl border border-white/5 bg-slate-950 px-4 py-3">
                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ e((string) $run['output']) }}</pre>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                No process history has been recorded yet.
            </div>
        @endforelse
    </div>
</div>
