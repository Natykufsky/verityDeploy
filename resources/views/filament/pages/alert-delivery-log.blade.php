<div class="space-y-3">
    @forelse ($deliveries as $delivery)
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 text-sm dark:border-white/10">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border px-2 py-1 text-xs font-semibold uppercase tracking-wide
                    @if ($delivery['status'] === 'sent') border-emerald-500/30 bg-emerald-500/10 text-emerald-300
                    @elseif ($delivery['status'] === 'failed') border-rose-500/30 bg-rose-500/10 text-rose-300
                    @else border-amber-500/30 bg-amber-500/10 text-amber-300
                    @endif">
                    {{ ucfirst($delivery['status']) }}
                </span>
                <span class="text-slate-200">{{ $delivery['title'] }}</span>
            </div>

            <div class="mt-3 grid gap-2 text-slate-300 sm:grid-cols-2">
                <div><span class="text-slate-500">Channel</span> {{ $delivery['channel'] }}</div>
                <div><span class="text-slate-500">Target</span> {{ $delivery['target'] ?? 'N/A' }}</div>
                <div><span class="text-slate-500">Level</span> {{ $delivery['level'] }}</div>
                <div><span class="text-slate-500">When</span> {{ $delivery['delivered_at'] }}</div>
            </div>

            @if (! empty($delivery['error_message']))
                <p class="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-rose-200">
                    {{ $delivery['error_message'] }}
                </p>
            @endif
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-4 text-sm text-slate-400">
            No alert deliveries yet.
        </div>
    @endforelse
</div>
