<div
    wire:poll.4s
    x-data="{
        scrollToBottom() {
            const scroller = this.$refs.scroller;

            if (scroller) {
                scroller.scrollTop = scroller.scrollHeight;
            }
        },
    }"
    x-init="scrollToBottom(); const autoScroll = setInterval(() => scrollToBottom(), 500);"
    class="overflow-hidden rounded-2xl border border-emerald-500/15 bg-slate-950 text-slate-100 shadow-2xl shadow-slate-950/30"
>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-500/10 bg-slate-900/80 px-5 py-4">
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.3em] text-emerald-300/80">Deployment terminal</p>
            <h3 class="mt-1 text-lg font-semibold text-white">
                {{ $deployment->site->name }}
            </h3>
            <p class="font-mono text-xs text-slate-400">
                {{ $deployment->site->server?->name ?? 'No server attached' }} - branch {{ $deployment->branch ?? 'main' }} - source {{ $deployment->source }}
            </p>
        </div>

        <div class="flex items-center gap-2 font-mono text-xs">
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-slate-300">
                #{{ $deployment->id }}
            </span>
            <span @class([
                'rounded-full px-3 py-1 font-semibold uppercase tracking-[0.2em]',
                'bg-emerald-500/15 text-emerald-300' => $deployment->status === 'successful',
                'bg-amber-500/15 text-amber-300' => $deployment->status === 'running',
                'bg-rose-500/15 text-rose-300' => $deployment->status === 'failed',
                'bg-slate-500/15 text-slate-300' => ! in_array($deployment->status, ['successful', 'running', 'failed'], true),
            ])>
                {{ $deployment->status }}
            </span>
        </div>
    </div>

    <div x-ref="scroller" class="max-h-[34rem] space-y-5 overflow-auto px-5 py-5 font-mono text-sm leading-6">
        <div class="rounded-xl border border-white/5 bg-black/40 px-4 py-3">
            <p class="text-emerald-300">
                $ verityDeploy deploy {{ $deployment->site->name }} --branch={{ $deployment->branch ?? 'main' }}
            </p>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-slate-400">
                <span>Commit: {{ $deployment->commit_hash ?? 'pending' }}</span>
                <span>Release: {{ $deployment->release_path ?? 'pending' }}</span>
                <span>Started: {{ $deployment->started_at?->format('Y-m-d H:i:s') ?? 'queued' }}</span>
                <span>Refreshed: {{ now()->format('H:i:s') }}</span>
            </div>
        </div>

        @forelse ($lines as $line)
            <details @class([
                'group rounded-2xl border border-white/5 bg-slate-900/70 px-4 py-4 transition-all duration-300',
                'border-emerald-400/30 bg-emerald-400/10 shadow-[0_0_0_1px_rgba(52,211,153,0.25),0_0_30px_rgba(16,185,129,0.14)] ring-1 ring-emerald-400/30' => $line['is_running'],
            ]) {{ $line['is_running'] ? 'open' : '' }}>
                <summary @class([
                    'flex cursor-pointer list-none flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.24em]',
                    'text-emerald-100' => $line['is_running'],
                    'text-slate-400' => ! $line['is_running'],
                ])>
                    <span class="inline-flex items-center gap-2">
                        @if ($line['is_running'])
                            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.9)] animate-pulse"></span>
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
                </summary>

                <div class="mt-3 rounded-xl border border-white/5 bg-black/50 px-4 py-3">
                    <div @class([
                        'font-medium',
                        'text-emerald-200' => $line['is_running'],
                        'text-slate-500' => ! $line['is_running'],
                    ])>
                        {{ $line['command'] }}
                    </div>

                    @if (filled($line['output']))
                        <pre @class([
                            'mt-3 whitespace-pre-wrap break-words',
                            'text-emerald-50' => $line['is_running'],
                            'text-slate-100' => ! $line['is_running'],
                        ])>{{ $line['output'] }}</pre>
                    @else
                        <div class="mt-3 text-slate-500">No terminal output yet.</div>
                    @endif
                </div>
            </details>
        @empty
            <div class="rounded-2xl border border-dashed border-emerald-500/20 bg-black/30 px-4 py-6 text-slate-400">
                Waiting for deployment steps to begin.
            </div>
        @endforelse

        @if (filled($deployment->output))
            <section class="rounded-2xl border border-white/5 bg-slate-900/70 px-4 py-4">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.24em] text-slate-400">
                    <span class="text-emerald-300">$</span>
                    <span>Full log</span>
                </div>

                <pre class="mt-3 whitespace-pre-wrap break-words text-slate-200">{{ $deployment->output }}</pre>
            </section>
        @endif
    </div>
</div>
