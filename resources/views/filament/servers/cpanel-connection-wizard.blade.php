@php
    $wizardLog = collect($this->wizardLog ?? []);
    $recentRuns = $record->cpanelWizardRuns()->latest('started_at')->limit(5)->get();
@endphp

<div class="mx-auto max-h-[400px] max-w-[1100px] space-y-6 overflow-y-auto pr-1">
    <div class="rounded-2xl border border-sky-500/15 bg-sky-500/10 p-4 text-sm text-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-2">
                <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-sky-100">Wizard workspace</span>
                <p class="text-lg font-semibold text-white">cPanel connection wizard</p>
            </div>
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200">{{ $recentRuns->count() }} saved runs</div>
        </div>
        <p class="mt-3 leading-6 text-slate-300">
            Use the wizard action above to discover the SSH port, validate the cPanel API, and run the server provisioning preflight in one pass.
        </p>
    </div>

    @if ($wizardLog->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-6 text-sm text-slate-400 dark:border-white/10">
            No wizard run has been recorded yet. Click <span class="font-semibold text-slate-200">Run cPanel wizard</span> to generate a step-by-step result log.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($wizardLog as $entry)
                <article class="w-full max-w-full min-w-0 rounded-2xl border border-white/5 bg-slate-950/70 p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-slate-100">{{ $entry['step'] ?? 'Step' }}</h3>
                            <div class="text-xs text-slate-400">{{ $entry['timestamp'] ?? '' }}</div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]
                            {{ ($entry['status'] ?? 'running') === 'successful'
                                ? 'bg-emerald-500/15 text-emerald-300'
                                : (($entry['status'] ?? 'running') === 'failed'
                                    ? 'bg-rose-500/15 text-rose-300'
                                    : 'bg-amber-500/15 text-amber-300') }}">
                            {{ $entry['status'] ?? 'running' }}
                        </span>
                    </div>

                    @if (($entry['status'] ?? 'running') === 'failed')
                        <details class="mt-4 rounded-xl border border-rose-500/15 bg-rose-500/10">
                            <summary class="cursor-pointer list-none px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-rose-100">
                                Failure details
                            </summary>
                            <pre class="max-w-full min-w-0 overflow-x-auto whitespace-pre-wrap break-words break-all border-t border-rose-500/10 bg-black/30 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $entry['message'] ?? '' }}</pre>
                        </details>
                    @else
                        <pre class="mt-4 w-full max-w-full min-w-0 overflow-x-auto whitespace-pre-wrap break-words break-all rounded-xl border border-white/5 bg-black/30 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $entry['message'] ?? '' }}</pre>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/60 p-4 text-sm text-slate-300 dark:border-white/10">
        <div class="flex items-center justify-between gap-3">
            <p class="font-semibold text-white">Recent audit history</p>
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Saved cPanel wizard runs</p>
        </div>

        @if ($recentRuns->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-slate-200/10 bg-slate-950/40 p-4 text-slate-400 dark:border-white/10">
                No saved cPanel wizard runs yet.
            </div>
        @else
            <div class="mt-4 max-h-[300px] space-y-3 overflow-y-auto pr-1">
                @foreach ($recentRuns as $run)
                    <article class="w-full max-w-full min-w-0 rounded-xl border border-white/5 bg-black/25 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <h4 class="text-sm font-semibold text-slate-100">{{ $run->wizard_type_label }}</h4>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">
                                    Started {{ $run->started_at_label }}
                                    @if ($run->finished_at)
                                        <span class="mx-1 text-slate-600">·</span> Finished {{ $run->finished_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]
                                {{ $run->status === 'successful'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : ($run->status === 'failed'
                                        ? 'bg-rose-500/15 text-rose-300'
                                        : 'bg-amber-500/15 text-amber-300') }}">
                                {{ $run->status }}
                            </span>
                        </div>

                        <div class="mt-4 w-full max-w-full min-w-0 rounded-xl border border-white/5 bg-black/30 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Summary</p>
                            <pre class="mt-2 w-full max-w-full min-w-0 overflow-x-auto whitespace-pre-wrap break-words break-all font-mono text-xs leading-6 text-slate-100">{{ $run->summary ?? $run->error_message ?? 'No summary available.' }}</pre>
                        </div>

                        @if (filled($run->recovery_hint))
                            <div class="mt-3 rounded-xl border border-amber-500/15 bg-amber-500/10 px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-amber-300">Recovery guidance</p>
                                <p class="mt-2 text-sm leading-6 text-amber-100">{{ $run->recovery_hint }}</p>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
