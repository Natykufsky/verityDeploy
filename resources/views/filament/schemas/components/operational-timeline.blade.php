@php
    $timeline = collect($record->operational_timeline ?? []);
    $filter = $this->timelineFilter ?? 'all';

    if ($filter !== 'all') {
        $timeline = $timeline->where('type', $filter);
    }
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            wire:click="$set('timelineFilter', 'all')"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $filter === 'all' ? 'bg-sky-500 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}"
        >
            All events
        </button>
        <button
            type="button"
            wire:click="$set('timelineFilter', 'connection')"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $filter === 'connection' ? 'bg-emerald-500 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}"
        >
            Connection tests
        </button>
        <button
            type="button"
            wire:click="$set('timelineFilter', 'health')"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $filter === 'health' ? 'bg-amber-500 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}"
        >
            Health checks
        </button>
    </div>

    @if ($timeline->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-5 text-sm text-slate-400 dark:border-white/10">
            No timeline events match the selected filter.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($timeline as $item)
                <article class="rounded-2xl border border-white/5 bg-slate-950/70 p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-100">{{ $item['title'] ?? 'Event' }}</h3>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide {{ ($item['type'] ?? 'gray') === 'connection' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">
                                    {{ $item['type'] ?? 'event' }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide
                                    {{ ($item['status'] ?? 'gray') === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : (($item['status'] ?? 'gray') === 'failed' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300') }}">
                                    {{ $item['status'] ?? 'unknown' }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-400">
                                {{ filled($item['tested_at'] ?? null) ? \Illuminate\Support\Carbon::parse($item['tested_at'])->toDayDateTimeString() : '' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-black/30 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-slate-500">Command</div>
                            <div class="mt-1 font-mono text-xs text-slate-100">{{ $item['command'] ?? 'n/a' }}</div>
                        </div>

                        @if (! empty($item['metrics']))
                            <div class="rounded-xl bg-black/30 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Metrics</div>
                                <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    @foreach ($item['metrics'] as $metricKey => $metricValue)
                                        <div class="rounded-lg bg-slate-900/70 px-2 py-1.5">
                                            <dt class="text-slate-500">{{ $metricKey }}</dt>
                                            <dd class="font-medium text-slate-100">{{ is_array($metricValue) ? json_encode($metricValue) : $metricValue }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endif

                        @if (filled($item['output'] ?? null))
                            <div class="md:col-span-2">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Output</div>
                                <pre class="mt-2 whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $item['output'] }}</pre>
                            </div>
                        @endif

                        @if (filled($item['error_message'] ?? null))
                            <div class="md:col-span-2">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Error</div>
                                <pre class="mt-2 whitespace-pre-wrap break-words rounded-xl border border-rose-500/20 bg-rose-950/30 px-4 py-3 font-mono text-xs leading-6 text-rose-100">{{ $item['error_message'] }}</pre>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
