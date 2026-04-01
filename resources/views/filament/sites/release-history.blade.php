@php
    $releases = collect($record->recent_admin_deployments ?? [])
        ->sortByDesc(fn (array $release) => filled($release['started_at'] ?? null) ? \Illuminate\Support\Carbon::parse($release['started_at']) : \Illuminate\Support\Carbon::createFromTimestamp(0))
        ->values();
@endphp

<div
    x-data="{ expanded: false }"
    class="space-y-4"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Release history</span>
                <x-info-tooltip text="Release entries stay collapsible so the history remains compact while preserving logs and errors." label="Release history help" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ $releases->count() }} releases
            </div>
            @if ($releases->isNotEmpty())
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
        @forelse ($releases as $index => $release)
            @php
                $status = $release['status'] ?? 'unknown';
                $opened = $index === 0;
                $startedAt = filled($release['started_at'] ?? null) ? \Illuminate\Support\Carbon::parse($release['started_at']) : null;
                $finishedAt = filled($release['finished_at'] ?? null) ? \Illuminate\Support\Carbon::parse($release['finished_at']) : null;
            @endphp

            <details @class([
                'deployment-frost-card rounded-2xl border border-white/5 p-4',
                'ring-1 ring-cyan-400/30 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)]' => $opened,
            ]) x-bind:open="expanded || {{ $opened ? 'true' : 'false' }}">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-white">
                                {{ $startedAt?->format('M d, Y H:i:s') ?? 'Release' }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ $status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : ($status === 'running' ? 'bg-cyan-500/15 text-cyan-300' : ($status === 'failed' ? 'bg-rose-500/15 text-rose-300' : 'bg-slate-500/15 text-slate-300')) }}">
                                {{ $status }}
                            </span>
                            @if (filled($release['source'] ?? null))
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                                    {{ $release['source'] }}
                                </span>
                            @endif
                            @if (filled($release['branch'] ?? null))
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">
                                    {{ $release['branch'] }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] uppercase tracking-[0.18em] text-slate-500">
                            <span>commit: {{ \Illuminate\Support\Str::limit((string) ($release['commit_hash'] ?? 'n/a'), 12) }}</span>
                            <span>·</span>
                            <span>{{ filled($release['release_path'] ?? null) ? basename((string) $release['release_path']) : 'Release details' }}</span>
                            <span>·</span>
                            <span>{{ $finishedAt ? 'Finished ' . $finishedAt->format('M d, Y H:i:s') : 'Running or pending' }}</span>
                        </div>
                        @if ($startedAt)
                            <div class="text-xs text-slate-400">{{ $startedAt->diffForHumans() }}</div>
                        @endif
                    </div>
                </summary>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>commit</span>
                            <x-info-tooltip text="The deployed revision hash for this release." label="Commit help" />
                        </div>
                        <div class="mt-1 font-mono text-xs text-slate-100">{{ $release['commit_hash'] ?? 'n/a' }}</div>
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>release path</span>
                            <x-info-tooltip text="The filesystem path where this release was created." label="Release path help" />
                        </div>
                        <div class="mt-1 break-all font-mono text-xs text-slate-100">{{ $release['release_path'] ?? 'n/a' }}</div>
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>started</span>
                            <x-info-tooltip text="When the deployment or release work began." label="Started help" />
                        </div>
                        <div class="mt-1 text-sm text-slate-100">{{ $startedAt?->format('M d, Y H:i:s') ?? 'n/a' }}</div>
                        @if ($startedAt)
                            <div class="mt-1 text-xs text-slate-400">{{ $startedAt->diffForHumans() }}</div>
                        @endif
                    </div>

                    <div class="rounded-xl bg-black/30 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>finished</span>
                            <x-info-tooltip text="When the release finished, or the latest recorded stop time." label="Finished help" />
                        </div>
                        <div class="mt-1 text-sm text-slate-100">{{ $finishedAt?->format('M d, Y H:i:s') ?? 'n/a' }}</div>
                        @if ($finishedAt)
                            <div class="mt-1 text-xs text-slate-400">{{ $finishedAt->diffForHumans() }}</div>
                        @endif
                    </div>

                    @if (filled($release['error_message'] ?? null))
                        <div class="md:col-span-2 rounded-xl border border-rose-500/20 bg-rose-950/30 p-3 text-sm text-rose-100">
                            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-rose-300/80">
                                <span>error</span>
                                <x-info-tooltip text="Any error message captured during the release run." label="Error help" />
                            </div>
                            <div class="mt-2">{{ $release['error_message'] }}</div>
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
                            <span>log output</span>
                            <x-info-tooltip text="Captured command output stays scrollable so the release card remains compact." label="Log output help" />
                        </div>
                        @if (filled($release['output'] ?? null))
                            <div class="mt-2 max-h-[220px] overflow-y-auto rounded-xl border border-white/5 bg-slate-950 px-4 py-3">
                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ e((string) $release['output']) }}</pre>
                            </div>
                        @else
                            <div class="mt-2 rounded-xl border border-dashed border-white/10 bg-black/20 px-4 py-3 text-sm text-slate-500">
                                No output captured for this release yet.
                            </div>
                        @endif
                    </div>
                </div>
            </details>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                No release history has been recorded yet.
            </div>
        @endforelse
    </div>
</div>
