@php
    $toneStyles = [
        'emerald' => [
            'border' => 'border-emerald-500/20',
            'dot' => 'bg-emerald-400',
            'value' => 'text-emerald-300',
        ],
        'rose' => [
            'border' => 'border-rose-500/20',
            'dot' => 'bg-rose-400',
            'value' => 'text-rose-300',
        ],
        'amber' => [
            'border' => 'border-amber-500/20',
            'dot' => 'bg-amber-400',
            'value' => 'text-amber-300',
        ],
        'slate' => [
            'border' => 'border-white/5',
            'dot' => 'bg-slate-400',
            'value' => 'text-slate-300',
        ],
    ];

    $runTone = $toneStyles[$latestRunTone] ?? $toneStyles['slate'];
@endphp

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Release cleanup</p>
                        <x-info-tooltip text="A snapshot of cleanup runs and the latest rotation state." label="Release cleanup help" />
                    </div>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        {{ $totalRuns }} runs
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Release cleanup history</h3>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (filled($latestRunUrl))
                    <a
                        href="{{ $latestRunUrl }}"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open site
                    </a>
                @endif

                <button
                    type="button"
                    wire:click="openLatestRun"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Latest cleanup
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Successful</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-300">{{ $successfulRuns }}</p>
                <p class="mt-1 text-xs text-slate-400">Cleanup runs</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300">{{ $failedRuns }}</p>
                <p class="mt-1 text-xs text-slate-400">Needs review</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Running</p>
                <p class="mt-1 text-2xl font-semibold text-amber-300">{{ $runningRuns }}</p>
                <p class="mt-1 text-xs text-slate-400">In progress</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 {{ $runTone['border'] }}">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $runTone['dot'] }}"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Latest</p>
                </div>
                <p class="mt-1 text-sm font-semibold {{ $runTone['value'] }}">{{ $latestRunLabel }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $latestRunWhen }}</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 {{ $runTone['border'] }}">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest cleanup</p>
            <x-info-tooltip text="The latest cleanup run and its current status summary." label="Latest cleanup help" />
            <p class="mt-1 text-sm font-semibold text-white">{{ $latestRunLabel }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $latestRunSummary }}</p>
        </div>
    </div>
</div>
