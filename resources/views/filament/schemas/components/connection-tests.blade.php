@php
    $tests = $record->connectionTests ?? collect();
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-2xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Recent check results</div>
                <p class="mt-1 text-sm text-slate-400">Connection and provisioning checks stay capped inside this card.</p>
            </div>
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ $tests->count() }} checks
            </div>
        </div>

        <div class="mt-4 max-h-[420px] space-y-3 overflow-y-auto pr-1">
            @forelse ($tests as $test)
                <article class="deployment-frost-panel rounded-2xl p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-white">{{ $test->tested_at?->format('Y-m-d H:i:s') ?? 'Pending' }}</p>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $test->command }}</p>
                        </div>
                        <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $test->status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}">
                            {{ $test->status }}
                        </div>
                    </div>

                    <div class="mt-3 max-h-[180px] overflow-y-auto rounded-xl border border-white/5 bg-black/25 p-3 font-mono text-xs leading-6 text-slate-100">
                        <pre class="whitespace-pre-wrap break-words">{{ $test->output ?: $test->error_message ?: 'No output captured.' }}</pre>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                    No checks have been recorded yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
