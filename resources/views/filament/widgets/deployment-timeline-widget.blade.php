@php
    $toneStyles = [
        'emerald' => [
            'border' => 'border-emerald-500/20',
            'dot' => 'bg-emerald-400',
            'value' => 'text-emerald-300',
            'chip' => 'bg-emerald-500/10 text-emerald-200 border-emerald-500/20',
        ],
        'rose' => [
            'border' => 'border-rose-500/20',
            'dot' => 'bg-rose-400',
            'value' => 'text-rose-300',
            'chip' => 'bg-rose-500/10 text-rose-200 border-rose-500/20',
        ],
        'amber' => [
            'border' => 'border-amber-500/20',
            'dot' => 'bg-amber-400',
            'value' => 'text-amber-300',
            'chip' => 'bg-amber-500/10 text-amber-200 border-amber-500/20',
        ],
        'slate' => [
            'border' => 'border-white/5',
            'dot' => 'bg-slate-400',
            'value' => 'text-slate-300',
            'chip' => 'bg-white/5 text-slate-200 border-white/10',
        ],
    ];

    $deploymentTone = $toneStyles[$latestDeploymentTone] ?? $toneStyles['slate'];
@endphp

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Deployment timeline</p>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        {{ $successfulCount }} successful
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Step-by-step deploy history</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    Review the latest deployment, scan each step as a status chip, and jump straight to the deployment record if something looks off.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (filled($latestDeploymentUrl))
                    <a
                        href="{{ $latestDeploymentUrl }}"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open deployment
                    </a>
                @endif

                <button
                    type="button"
                    wire:click="openLatestDeployment"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Latest deployment
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Successful</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-300">{{ $successfulCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Running</p>
                <p class="mt-1 text-2xl font-semibold text-amber-300">{{ $runningCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300">{{ $failedCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 {{ $deploymentTone['border'] }}">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full {{ $deploymentTone['dot'] }}"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest deployment</p>
            </div>
            <p class="mt-1 text-sm font-semibold {{ $deploymentTone['value'] }}">{{ $latestDeploymentLabel }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $latestDeploymentWhen }}</p>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $latestDeploymentSummary }}</p>
        </div>

        <div class="mt-4">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Step chips</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($stepChips as $chip)
                    <div class="rounded-full border px-3 py-2 text-xs font-semibold {{ $toneStyles[$chip['tone']]['chip'] }}">
                        <span>{{ $chip['label'] }}</span>
                        <span class="ml-2 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.16em] {{ $toneStyles[$chip['tone']]['chip'] }}">
                            {{ $chip['status'] }}
                        </span>
                    </div>
                @empty
                    <div class="rounded-xl border border-white/5 bg-black/20 px-4 py-3 text-sm text-slate-400">
                        No step history yet. The timeline will populate after the first deployment.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
