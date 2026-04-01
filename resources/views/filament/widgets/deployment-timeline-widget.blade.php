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
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Deployment timeline</p>
                        <x-info-tooltip text="A snapshot of recent deployment activity and the latest deployment state." label="Deployment timeline help" />
                    </div>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        {{ $successfulCount }} successful
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Step-by-step deploy history</h3>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
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
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                    <span>Successful</span>
                    <x-info-tooltip text="Number of successful deployments in the current list." label="Successful help" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-emerald-300">{{ $successfulCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                    <span>Running</span>
                    <x-info-tooltip text="Number of deployments currently running." label="Running help" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-amber-300">{{ $runningCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                    <span>Failed</span>
                    <x-info-tooltip text="Number of deployments that failed and need attention." label="Failed help" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-rose-300">{{ $failedCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 {{ $deploymentTone['border'] }}">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full {{ $deploymentTone['dot'] }}"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest deployment</p>
                <x-info-tooltip text="The most recent deployment and its current summary." label="Latest deployment help" />
            </div>
            <p class="mt-1 text-sm font-semibold {{ $deploymentTone['value'] }}">{{ $latestDeploymentLabel }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $latestDeploymentWhen }}</p>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $latestDeploymentSummary }}</p>
        </div>

        <div class="mt-4">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                <span>Step chips</span>
                <x-info-tooltip text="Each chip opens a deeper view of the corresponding deployment step." label="Step chips help" />
            </div>

            <div class="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                @forelse ($stepChips as $chip)
                    <button
                        type="button"
                        wire:click="openStepDetail({{ $latestDeploymentId }}, {{ $chip['sequence'] }})"
                        class="flex w-full items-center justify-between gap-3 rounded-full border px-3 py-2 text-left text-xs font-semibold transition hover:scale-[1.01] hover:shadow-sm sm:w-auto {{ $toneStyles[$chip['tone']]['chip'] }}"
                    >
                        <span class="truncate">{{ $chip['label'] }}</span>
                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.16em] {{ $toneStyles[$chip['tone']]['chip'] }}">
                            {{ $chip['status'] }}
                        </span>
                    </button>
                @empty
                    <div class="rounded-xl border border-white/5 bg-black/20 px-4 py-3 text-sm text-slate-400">
                        No step history yet. The timeline will populate after the first deployment.
                    </div>
                @endforelse
            </div>
        </div>

        @if (filled($selectedStepDetail))
            <div class="mt-5 rounded-2xl border border-white/10 bg-slate-950/95 p-5 shadow-2xl">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                    <div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.28em] text-slate-400">
                            <span>Step detail</span>
                            <x-info-tooltip text="Expanded details for the selected deployment step." label="Step detail help" />
                        </div>
                        <h4 class="mt-2 text-lg font-semibold text-white">{{ $selectedStepDetail['step_label'] }}</h4>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ $selectedStepDetail['deployment_label'] }} · Step {{ $selectedStepDetail['sequence'] }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeStepDetail"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Close
                    </button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                            <span>Status</span>
                            <x-info-tooltip text="The current status of the selected step." label="Status help" />
                        </div>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $selectedStepDetail['status'] }}</p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                            <span>Started</span>
                            <x-info-tooltip text="When the selected step started." label="Started help" />
                        </div>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $selectedStepDetail['started_at'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $selectedStepDetail['started_relative'] }}</p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                            <span>Finished</span>
                            <x-info-tooltip text="When the selected step finished." label="Finished help" />
                        </div>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $selectedStepDetail['finished_at'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $selectedStepDetail['finished_relative'] }}</p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                            <span>Exit code</span>
                            <x-info-tooltip text="The exit code returned by the step command." label="Exit code help" />
                        </div>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $selectedStepDetail['exit_code'] ?? 'n/a' }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-white/10 bg-black/30 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                            <span>Command</span>
                            <x-info-tooltip text="The command shown for the selected deployment step." label="Command help" />
                        </div>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText(@js($selectedStepDetail['command']))"
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-[11px] font-semibold text-slate-100 transition hover:bg-white/10"
                        >
                            Copy command
                        </button>
                    </div>
                    <pre class="mt-3 max-h-45 overflow-y-auto whitespace-pre-wrap wrap-break-word rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $selectedStepDetail['command'] }}</pre>
                </div>

                <div class="mt-4 rounded-xl border border-white/10 bg-black/30 p-4">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                        <span>Output</span>
                        <x-info-tooltip text="Captured command output for the selected deployment step." label="Output help" />
                    </div>
                    <pre class="mt-3 max-h-55 overflow-y-auto whitespace-pre-wrap wrap-break-word rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $selectedStepDetail['output'] ?: 'No output captured.' }}</pre>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-2 text-xs text-slate-400">
                        <p>Open the full deployment record for the complete command history and terminal output.</p>
                        <x-info-tooltip text="Use the deployment record for the full step history and log output." label="Open deployment help" />
                    </div>

                    <a
                        href="{{ $selectedStepDetail['url'] }}"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open deployment
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
