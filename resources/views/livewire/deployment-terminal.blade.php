<div id="deployment-terminal" class="max-h-[620px] space-y-4 overflow-y-auto pr-1">
    <div class="deployment-frost-card flex flex-wrap items-center justify-between gap-3 rounded-2xl px-4 py-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Live deployment logs</span>
                <x-info-tooltip text="The log stream for the current or most recent deployment run." label="Live deployment logs help" />
            </div>
            <div class="mt-1 text-sm font-semibold text-white">
                {{ count($lines) }} log line{{ count($lines) === 1 ? '' : 's' }} tracked
            </div>
        </div>
        <div class="text-xs text-slate-400">
            {{ $deployment->status === 'running' ? 'Live updates are on' : 'Historical logs' }}
        </div>
    </div>

    <div class="space-y-4">
    @forelse ($lines as $line)
        <details @class([
            'group deployment-frost-card rounded-2xl px-4 py-4 transition-all duration-300',
            'ring-1 ring-emerald-400/30 shadow-[0_0_0_1px_rgba(52,211,153,0.25),0_0_30px_rgba(16,185,129,0.14)]' => $line['is_running'],
        ]) {{ $line['is_running'] ? 'open' : '' }}>
            <summary @class([
                'flex cursor-pointer list-none flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.24em]',
                'text-emerald-100' => $line['is_running'],
                'text-slate-400' => ! $line['is_running'],
            ])>
                <span class="inline-flex items-center gap-2">
                    @if ($line['is_running'])
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.9)] animate-pulse"></span>
                    @elseif ($line['status'] === 'failed')
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-rose-300 shadow-[0_0_12px_rgba(251,113,133,0.8)]"></span>
                    @else
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-slate-500"></span>
                    @endif
                    <span>[{{ $line['timestamp'] }}]</span>
                </span>
                <span class="text-emerald-300">$</span>
                <span>{{ $line['label'] }}</span>
                <span @class([
                    'rounded-full px-2.5 py-1 font-semibold',
                    'bg-emerald-500/15 text-emerald-300' => $line['status'] === 'successful',
                    'bg-amber-500/15 text-amber-300' => $line['status'] === 'running',
                    'bg-rose-500/15 text-rose-300' => $line['status'] === 'failed',
                    'bg-slate-500/15 text-slate-300' => ! in_array($line['status'], ['successful', 'running', 'failed'], true),
                ])>
                    {{ $line['status'] }}
                </span>
                <span @class([
                    'font-semibold' => $line['is_running'],
                    'text-emerald-200' => $line['is_running'],
                    'text-slate-500' => ! $line['is_running'],
                ])>
                    {{ $line['duration'] }}
                </span>
                <span class="ml-auto text-slate-500 normal-case tracking-normal">
                    {{ $line['is_running'] ? 'Live' : ($line['status'] === 'failed' ? 'Open for recovery' : 'Collapsed by default') }}
                </span>
            </summary>

            <div class="deployment-frost-panel mt-3 space-y-3 rounded-xl px-4 py-3">
                <div class="flex items-center gap-2 text-sm font-medium" @class([
                    'text-emerald-200' => $line['is_running'],
                    'text-slate-300' => ! $line['is_running'] && $line['status'] !== 'failed',
                    'text-rose-200' => $line['status'] === 'failed',
                ])>
                    <span>{{ $line['command'] }}</span>
                    <x-info-tooltip text="The exact deployment command for this log entry." label="Command help" />
                </div>

                @if (filled($line['output']))
                    <pre @class([
                        'overflow-x-auto whitespace-pre-wrap break-words rounded-xl border px-4 py-3 text-xs leading-6',
                        'border-emerald-400/20 bg-emerald-400/10 text-emerald-50' => $line['is_running'],
                        'border-rose-400/20 bg-rose-400/10 text-rose-50' => $line['status'] === 'failed',
                        'border-white/5 bg-slate-950 text-slate-100' => ! $line['is_running'] && $line['status'] !== 'failed',
                    ])>{{ $line['output'] }}</pre>
                @elseif ($line['status'] === 'failed' && filled($deployment->recovery_hint))
                    <div class="rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm leading-6 text-rose-50">
                        {{ $deployment->recovery_hint }}
                    </div>
                @else
                    <div class="flex items-center gap-2 text-slate-500">
                        <span>No terminal output yet.</span>
                        <x-info-tooltip text="No output has been captured for this deployment step yet." label="No output help" />
                    </div>
                @endif
            </div>
        </details>
    @empty
        <div class="rounded-2xl border border-dashed border-emerald-500/20 bg-black/30 px-4 py-6 text-slate-400">
            Waiting for deployment steps to begin.
        </div>
    @endforelse
    </div>
</div>
