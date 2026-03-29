<div class="rounded-2xl border border-white/5 bg-slate-950/90 p-5 text-slate-100 shadow-lg shadow-slate-950/20">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div>
            <p class="text-[11px] uppercase tracking-[0.28em] text-emerald-300/80">Activity log</p>
            <h3 class="mt-1 text-base font-semibold text-white">Recent settings changes</h3>
        </div>
        <p class="text-xs text-slate-400">A compact trail of the most recent saves.</p>
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($changes as $change)
            <article class="rounded-xl border border-white/5 bg-white/5 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-slate-100">{{ $change['summary'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $change['user'] }} - {{ $change['timestamp'] }}
                        </p>
                    </div>
                    <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-emerald-200">
                        saved
                    </span>
                </div>

                @if (filled($change['anchors']))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($change['anchors'] as $anchor)
                            <a
                                href="#{{ $anchor['anchor'] }}"
                                class="rounded-full border border-white/5 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-slate-300 transition hover:border-emerald-400/30 hover:bg-emerald-400/10 hover:text-emerald-100"
                            >
                                Jump to {{ $anchor['field'] }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if (filled($change['changes']))
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($change['changes'] as $field => $values)
                            <div class="rounded-lg border border-white/5 bg-slate-950/80 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">{{ \Illuminate\Support\Str::headline($field) }}</p>
                                <p class="mt-1 text-xs text-slate-300">
                                    <span class="text-slate-500">from</span> {{ $values['from'] ?? 'n/a' }}
                                    <span class="mx-1 text-slate-600">→</span>
                                    <span class="text-slate-500">to</span> {{ $values['to'] ?? 'n/a' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-700 bg-slate-950/70 px-4 py-6 text-sm text-slate-400">
                No settings changes have been recorded yet.
            </div>
        @endforelse
    </div>
</div>
