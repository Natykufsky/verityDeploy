<?php
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
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Release cleanup</p>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        <?php echo e($totalRuns); ?> runs
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Release cleanup history</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    Track recent cleanup activity, see the latest rotation status, and jump to the affected site.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($latestRunUrl)): ?>
                    <a
                        href="<?php echo e($latestRunUrl); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Open site
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                <p class="mt-1 text-2xl font-semibold text-emerald-300"><?php echo e($successfulRuns); ?></p>
                <p class="mt-1 text-xs text-slate-400">Cleanup runs</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300"><?php echo e($failedRuns); ?></p>
                <p class="mt-1 text-xs text-slate-400">Needs review</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Running</p>
                <p class="mt-1 text-2xl font-semibold text-amber-300"><?php echo e($runningRuns); ?></p>
                <p class="mt-1 text-xs text-slate-400">In progress</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 <?php echo e($runTone['border']); ?>">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?php echo e($runTone['dot']); ?>"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Latest</p>
                </div>
                <p class="mt-1 text-sm font-semibold <?php echo e($runTone['value']); ?>"><?php echo e($latestRunLabel); ?></p>
                <p class="mt-1 text-xs text-slate-400"><?php echo e($latestRunWhen); ?></p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 <?php echo e($runTone['border']); ?>">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest cleanup</p>
            <p class="mt-1 text-sm font-semibold text-white"><?php echo e($latestRunLabel); ?></p>
            <p class="mt-2 text-sm leading-6 text-slate-400"><?php echo e($latestRunSummary); ?></p>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/release-cleanup-overview-card.blade.php ENDPATH**/ ?>