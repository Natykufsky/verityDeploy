@php
    $summary = [
        'Status' => $record->status,
        'Connection' => $record->connection_type,
        'SSH port' => $record->ssh_port ?? 'n/a',
        'Last connected' => $record->last_connected_at?->format('Y-m-d H:i:s') ?? 'Never',
    ];

    $metrics = $record->metrics ?? [];
@endphp

<div
    x-data="verityServerMetrics({
        bridgeUrl: @js($bridge['url'] ?? null),
        summary: @js($summary),
        metrics: @js($metrics),
    })"
    x-init="init()"
    wire:ignore
    class="deployment-frost-card rounded-3xl p-5"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Server metrics</div>
            <p class="mt-1 text-sm text-slate-400">A live snapshot of the current connection and runtime state.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
                :class="bridgeStatusClasses()"
            >
                <span
                    x-show="bridgeState === 'connecting' || bridgeState === 'reconnecting'"
                    x-cloak
                    class="inline-flex h-2.5 w-2.5 items-center justify-center rounded-full border border-current/40"
                >
                    <span class="h-1.5 w-1.5 animate-spin rounded-full border border-current border-t-transparent"></span>
                </span>
                <span
                    x-show="bridgeState !== 'connecting' && bridgeState !== 'reconnecting'"
                    x-cloak
                    class="h-2 w-2 rounded-full"
                    :class="bridgeDotClasses()"
                ></span>
                <span x-text="bridgeStatusLabel()"></span>
            </span>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                <span x-text="Object.keys(metrics || {}).length"></span> metric groups
            </span>
        </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="([label, value], index) in summaryEntries()" :key="label">
            <div
                class="deployment-frost-panel rounded-2xl p-4 transition-all duration-300"
                :class="pulseToken ? 'ring-1 ring-emerald-400/20 shadow-[0_0_0_1px_rgba(16,185,129,0.14),0_0_24px_rgba(16,185,129,0.08)]' : ''"
            >
                <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500" x-text="label"></div>
                <div class="mt-2 text-sm font-semibold text-white" x-text="value"></div>
            </div>
        </template>
    </div>

    <div class="mt-5">
        <div class="flex items-center justify-between gap-3">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Metrics</div>
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span x-text="bridgeState === 'connected' ? 'live updates' : 'bridge idle'"></span>
            </div>
        </div>

        <template x-if="Object.keys(metrics || {}).length === 0">
            <div class="mt-3 rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                No metrics have been captured yet.
            </div>
        </template>

        <div x-show="Object.keys(metrics || {}).length > 0" x-cloak class="mt-3 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
            <template x-for="([metricKey, metricValue], metricIndex) in metricGroups()" :key="metricKey">
                <details class="deployment-frost-panel rounded-2xl p-4" :open="metricIndex === 0">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-white" x-text="metricKey"></div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Metric group</div>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300" x-text="Array.isArray(metricValue) ? `${metricValue.length} items` : 'Value'"></span>
                    </summary>

                    <div class="mt-4 rounded-xl border border-white/5 bg-black/25 p-3">
                        <template x-if="metricEntries(metricValue).length > 0">
                            <div class="grid gap-2 text-sm">
                                <template x-for="([subKey, subValue]) in metricEntries(metricValue)" :key="subKey">
                                    <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl bg-slate-900/70 px-3 py-2" :class="pulseToken ? 'ring-1 ring-cyan-400/15' : ''">
                                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500" x-text="subKey"></div>
                                        <div class="font-medium text-slate-100" x-text="metricScalar(subValue)"></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="metricEntries(metricValue).length === 0">
                            <div class="font-mono text-xs leading-6 text-slate-100" x-text="metricScalar(metricValue)"></div>
                        </template>
                    </div>
                </details>
            </template>
        </div>
    </div>
</div>
