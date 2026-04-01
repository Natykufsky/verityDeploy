@php
    $backups = collect($record->recent_admin_backups ?? []);
@endphp

<div
    x-data="{ expanded: false }"
    class="space-y-4"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Backup history</span>
                <x-info-tooltip text="Backup entries stay collapsible so the history remains compact while preserving logs and restore details." label="Backup history help" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ $backups->count() }} backups
            </div>
            @if ($backups->isNotEmpty())
                <button
                    type="button"
                    @click="expanded = ! expanded"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-300 transition hover:border-cyan-400/30 hover:bg-cyan-500/10 hover:text-cyan-100"
                    x-text="expanded ? 'Collapse all' : 'Expand all'"
                ></button>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($backups as $index => $backup)
            @php
                $status = $backup['status'] ?? 'unknown';
                $opened = $index === 0;
                $startedAt = filled($backup['started_at'] ?? null) ? \Illuminate\Support\Carbon::parse($backup['started_at'])->toDayDateTimeString() : null;
                $finishedAt = filled($backup['finished_at'] ?? null) ? \Illuminate\Support\Carbon::parse($backup['finished_at'])->toDayDateTimeString() : null;
            @endphp

            <details @class([
                'deployment-frost-card rounded-2xl border border-white/5 p-4',
                'ring-1 ring-cyan-400/30 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)]' => $opened,
            ]) x-bind:open="expanded || {{ $opened ? 'true' : 'false' }}">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-white">
                                {{ $startedAt ?? 'Backup' }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ $status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : ($status === 'running' ? 'bg-cyan-500/15 text-cyan-300' : ($status === 'failed' ? 'bg-rose-500/15 text-rose-300' : 'bg-slate-500/15 text-slate-300')) }}">
                                {{ $status }}
                            </span>
                            @if (filled($backup['operation'] ?? null))
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                                    {{ $backup['operation'] }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] uppercase tracking-[0.18em] text-slate-500">
                            <span>snapshot: {{ \Illuminate\Support\Str::limit((string) ($backup['snapshot_path'] ?? 'n/a'), 20) }}</span>
                            <span>•</span>
                            <span>{{ filled($backup['restored_release_path'] ?? null) ? \Illuminate\Support\Str::limit((string) $backup['restored_release_path'], 20) : 'No restore path' }}</span>
                            <span>•</span>
                            <span>{{ $finishedAt ? 'Finished ' . $finishedAt : 'Running or pending' }}</span>
                        </div>
                    </div>
                </summary>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>snapshot path</span>
                            <x-info-tooltip text="The on-disk location of the backup snapshot." label="Snapshot path help" />
                        </div>
                        <div class="mt-1 break-all font-mono text-xs text-slate-100">{{ $backup['snapshot_path'] ?? 'n/a' }}</div>
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>restored release</span>
                            <x-info-tooltip text="The release path restored from this backup, if applicable." label="Restored release help" />
                        </div>
                        <div class="mt-1 break-all font-mono text-xs text-slate-100">{{ $backup['restored_release_path'] ?? 'n/a' }}</div>
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>started</span>
                            <x-info-tooltip text="When the backup job began." label="Started help" />
                        </div>
                        <div class="mt-1 text-sm text-slate-100">{{ $startedAt ?? 'n/a' }}</div>
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>finished</span>
                            <x-info-tooltip text="When the backup job finished, or the latest recorded stop time." label="Finished help" />
                        </div>
                        <div class="mt-1 text-sm text-slate-100">{{ $finishedAt ?? 'n/a' }}</div>
                    </div>

                    @if (filled($backup['error_message'] ?? null))
                        <div class="md:col-span-2 rounded-xl border border-rose-500/20 bg-rose-950/30 p-3 text-sm text-rose-100">
                            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-rose-300/80">
                                <span>error</span>
                                <x-info-tooltip text="Any error message captured during the backup run." label="Error help" />
                            </div>
                            <div class="mt-2">{{ $backup['error_message'] }}</div>
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>output</span>
                            <x-info-tooltip text="Captured backup output stays scrollable so the backup card remains compact." label="Output help" />
                        </div>
                        @if (filled($backup['output'] ?? null))
                            <div class="mt-2 max-h-[220px] overflow-y-auto rounded-xl border border-white/5 bg-slate-950 px-4 py-3">
                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ e((string) $backup['output']) }}</pre>
                            </div>
                        @else
                            <div class="mt-2 rounded-xl border border-dashed border-white/10 bg-black/20 px-4 py-3 text-sm text-slate-500">
                                No output captured for this backup yet.
                            </div>
                        @endif
                    </div>
                </div>
            </details>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                No backup history has been recorded yet.
            </div>
        @endforelse
    </div>
</div>
