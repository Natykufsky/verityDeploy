<?php
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
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Deployment timeline</p>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        <?php echo e($successfulCount); ?> successful
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Step-by-step deploy history</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    Review the latest deployment, scan each step as a status chip, and jump straight to the deployment record if something looks off.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($latestDeploymentUrl)): ?>
                    <a
                        href="<?php echo e($latestDeploymentUrl); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open deployment
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                <p class="mt-1 text-2xl font-semibold text-emerald-300"><?php echo e($successfulCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Running</p>
                <p class="mt-1 text-2xl font-semibold text-amber-300"><?php echo e($runningCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300"><?php echo e($failedCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Deployments</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 <?php echo e($deploymentTone['border']); ?>">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full <?php echo e($deploymentTone['dot']); ?>"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest deployment</p>
            </div>
            <p class="mt-1 text-sm font-semibold <?php echo e($deploymentTone['value']); ?>"><?php echo e($latestDeploymentLabel); ?></p>
            <p class="mt-1 text-xs text-slate-400"><?php echo e($latestDeploymentWhen); ?></p>
            <p class="mt-3 text-sm leading-6 text-slate-300"><?php echo e($latestDeploymentSummary); ?></p>
        </div>

        <div class="mt-4">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Step chips</p>

            <div class="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $stepChips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button
                        type="button"
                        wire:click="openStepDetail(<?php echo e($latestDeploymentId); ?>, <?php echo e($chip['sequence']); ?>)"
                        class="flex w-full items-center justify-between gap-3 rounded-full border px-3 py-2 text-left text-xs font-semibold transition hover:scale-[1.01] hover:shadow-sm sm:w-auto <?php echo e($toneStyles[$chip['tone']]['chip']); ?>"
                    >
                        <span class="truncate"><?php echo e($chip['label']); ?></span>
                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.16em] <?php echo e($toneStyles[$chip['tone']]['chip']); ?>">
                            <?php echo e($chip['status']); ?>

                        </span>
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="rounded-xl border border-white/5 bg-black/20 px-4 py-3 text-sm text-slate-400">
                        No step history yet. The timeline will populate after the first deployment.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($selectedStepDetail)): ?>
            <div class="mt-5 rounded-2xl border border-white/10 bg-slate-950/95 p-5 shadow-2xl">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Step detail</p>
                        <h4 class="mt-2 text-lg font-semibold text-white"><?php echo e($selectedStepDetail['step_label']); ?></h4>
                        <p class="mt-1 text-sm text-slate-400">
                            <?php echo e($selectedStepDetail['deployment_label']); ?> · Step <?php echo e($selectedStepDetail['sequence']); ?>

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
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Status</p>
                        <p class="mt-1 text-sm font-semibold text-white"><?php echo e($selectedStepDetail['status']); ?></p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Started</p>
                        <p class="mt-1 text-sm font-semibold text-white"><?php echo e($selectedStepDetail['started_at']); ?></p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Finished</p>
                        <p class="mt-1 text-sm font-semibold text-white"><?php echo e($selectedStepDetail['finished_at']); ?></p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Exit code</p>
                        <p class="mt-1 text-sm font-semibold text-white"><?php echo e($selectedStepDetail['exit_code'] ?? 'n/a'); ?></p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-white/10 bg-black/30 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Command</p>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText(<?php echo \Illuminate\Support\Js::from($selectedStepDetail['command'])->toHtml() ?>)"
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-[11px] font-semibold text-slate-100 transition hover:bg-white/10"
                        >
                            Copy command
                        </button>
                    </div>
                    <pre class="mt-3 max-h-[180px] overflow-y-auto whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100"><?php echo e($selectedStepDetail['command']); ?></pre>
                </div>

                <div class="mt-4 rounded-xl border border-white/10 bg-black/30 p-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Output</p>
                    <pre class="mt-3 max-h-[220px] overflow-y-auto whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100"><?php echo e($selectedStepDetail['output'] ?: 'No output captured.'); ?></pre>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-400">
                        Open the full deployment record for the complete command history and terminal output.
                    </p>

                    <a
                        href="<?php echo e($selectedStepDetail['url']); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open deployment
                    </a>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/deployment-timeline-widget.blade.php ENDPATH**/ ?>