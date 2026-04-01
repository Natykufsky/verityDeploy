<div id="deployment-steps" class="space-y-4">
    <div class="deployment-frost-card flex flex-wrap items-center justify-between gap-3 rounded-2xl px-4 py-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Deployment progress</span>
                <x-info-tooltip text="The current step count and progress summary for the deployment." label="Deployment progress help" />
            </div>
            <div class="mt-1 text-sm font-semibold text-white">
                {{ $record->steps->count() }} step{{ $record->steps->count() === 1 ? '' : 's' }} tracked
            </div>
        </div>
        <div class="text-xs text-slate-400">
            {{ $record->step_progress['summary'] }}
        </div>
    </div>

    @forelse ($record->steps as $step)
        @php
            $isRunning = $step->status === 'running';
        @endphp

        <details @class([
            'group deployment-frost-card rounded-2xl px-4 py-4 transition-all duration-300',
            'ring-1 ring-emerald-400/30 shadow-[0_0_0_1px_rgba(52,211,153,0.25),0_0_30px_rgba(16,185,129,0.14)]' => $isRunning,
        ]) {{ $isRunning ? 'open' : '' }}>
            <summary @class([
                'flex cursor-pointer list-none flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.24em]',
                'text-emerald-100' => $isRunning,
                'text-slate-400' => ! $isRunning,
            ])>
                <span class="inline-flex items-center gap-2">
                    @if ($isRunning)
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.9)] animate-pulse"></span>
                    @elseif ($step->status === 'failed')
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-rose-300 shadow-[0_0_12px_rgba(251,113,133,0.8)]"></span>
                    @else
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-slate-500"></span>
                    @endif
                    <span>[{{ $step->started_at?->format('H:i:s') ?? '--:--:--' }}]</span>
                </span>
                <span class="text-emerald-300">$</span>
                <span>{{ $step->label }}</span>
                <span @class([
                    'rounded-full px-2.5 py-1 font-semibold',
                    'bg-emerald-500/15 text-emerald-300' => $step->status === 'successful',
                    'bg-amber-500/15 text-amber-300' => $step->status === 'running',
                    'bg-rose-500/15 text-rose-300' => $step->status === 'failed',
                    'bg-slate-500/15 text-slate-300' => ! in_array($step->status, ['successful', 'running', 'failed'], true),
                ])>
                    {{ $step->status }}
                </span>
                <span @class([
                    'font-semibold' => $isRunning,
                    'text-emerald-200' => $isRunning,
                    'text-slate-500' => ! $isRunning,
                ])>
                    {{ $step->started_at && $step->finished_at ? $step->started_at->diffInSeconds($step->finished_at) . 's' : ($step->started_at?->diffInSeconds(now()) ?? 0) . 's' }}
                </span>
                <span class="ml-auto text-slate-500 normal-case tracking-normal">
                    {{ $isRunning ? 'Live' : ($step->status === 'failed' ? 'Open for recovery' : 'Collapsed by default') }}
                </span>
            </summary>

            <div class="deployment-frost-panel mt-3 space-y-3 rounded-xl px-4 py-3">
                <div class="flex items-center gap-2 text-sm font-medium" @class([
                    'text-emerald-200' => $isRunning,
                    'text-slate-300' => ! $isRunning && $step->status !== 'failed',
                    'text-rose-200' => $step->status === 'failed',
                ])>
                    <span>{{ $step->command }}</span>
                    <x-info-tooltip text="The command for this deployment step." label="Step command help" />
                </div>

                @if (filled($step->output))
                    <div class="max-h-72 overflow-y-auto rounded-xl border px-4 py-3 text-xs leading-6">
                        <pre @class([
                            'overflow-x-auto whitespace-pre-wrap break-words',
                            'border-emerald-400/20 bg-emerald-400/10 text-emerald-50' => $isRunning,
                            'border-rose-400/20 bg-rose-400/10 text-rose-50' => $step->status === 'failed',
                            'border-white/5 bg-slate-950 text-slate-100' => ! $isRunning && $step->status !== 'failed',
                        ])>{{ $step->output }}</pre>
                    </div>
                @elseif ($step->status === 'failed' && filled($record->recovery_hint))
                    <div class="rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm leading-6 text-rose-50">
                        {{ $record->recovery_hint }}
                    </div>
                @else
                    <div class="text-slate-500">No terminal output yet.</div>
                @endif
            </div>
        </details>
    @empty
        <div class="rounded-2xl border border-dashed border-emerald-500/20 bg-black/30 px-4 py-6 text-slate-400">
            Waiting for deployment steps to begin.
        </div>
    @endforelse
</div>
