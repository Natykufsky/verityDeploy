@php
    $tests = ($record->connectionTests ?? collect())->sortByDesc(fn ($test) => $test->tested_at ?? $test->created_at ?? null)->values();
@endphp

<div x-data="{ expanded: false }" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>recent check results</span>
                <x-info-tooltip text="Each check stays collapsible so recent results remain compact and readable." label="Recent check results help" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ $tests->count() }} checks
            </div>
            @if ($tests->isNotEmpty())
                <button
                    type="button"
                    @click="expanded = ! expanded"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-300 transition hover:border-cyan-400/30 hover:bg-cyan-500/10 hover:text-cyan-100"
                    x-text="expanded ? 'collapse all' : 'expand all'"
                ></button>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($tests as $index => $test)
            @php
                $opened = $index === 0;
            @endphp

            <details @class([
                'rounded-2xl border border-white/5 bg-white/5 p-4 shadow-lg shadow-black/10 backdrop-blur-md',
                'ring-1 ring-cyan-400/30 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)]' => $test->status === 'running',
            ]) x-bind:open="expanded || {{ $opened ? 'true' : 'false' }}">
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-white">{{ $test->tested_at?->format('M d, Y H:i:s') ?? 'Pending' }}</p>
                        @if ($test->tested_at)
                            <p class="text-xs text-slate-400">{{ $test->tested_at->diffForHumans() }}</p>
                        @endif
                        <p class="text-xs lowercase tracking-[0.2em] text-slate-100">{{ strtolower($test->command) }}</p>
                    </div>
                    <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $test->status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}">
                        {{ strtolower($test->status) }}
                    </div>
                </summary>

                <div class="mt-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-300">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-100">exit code:</span>
                                <x-info-tooltip text="The process exit code returned by the command." label="Exit code help" />
                            </div>
                            <div>{{ $test->exit_code ?? 'pending' }}</div>
                        </div>

                    <div class="mt-3 max-h-[180px] overflow-y-auto rounded-xl border border-white/5 bg-black/25 p-3 font-mono text-xs leading-6 text-slate-100">
                        <pre class="whitespace-pre-wrap break-words">{{ $test->output ?: $test->error_message ?: 'No output captured.' }}</pre>
                    </div>
                </div>
            </details>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                no checks have been recorded yet.
            </div>
        @endforelse
    </div>
</div>
