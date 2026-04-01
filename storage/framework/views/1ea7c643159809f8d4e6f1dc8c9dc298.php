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

    $driftTone = $toneStyles[$latestDriftTone] ?? $toneStyles['slate'];
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">GitHub drift</p>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        <?php echo e($driftCount); ?> drifted
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Webhook sync drift summary</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    See how many sites need webhook repair and jump straight to the affected GitHub settings.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="<?php echo e($driftUrl); ?>"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open drift filter
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($latestDriftSiteUrl)): ?>
                    <a
                        href="<?php echo e($latestDriftSiteUrl); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Latest site
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Provisioned</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-300"><?php echo e($provisionedCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Healthy webhooks</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Drifted</p>
                <p class="mt-1 text-2xl font-semibold text-amber-300"><?php echo e($driftCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Need sync</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300"><?php echo e($failedCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Remote hook errors</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 <?php echo e($driftTone['border']); ?>">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full <?php echo e($driftTone['dot']); ?>"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest drift</p>
            </div>
            <p class="mt-1 text-sm font-semibold <?php echo e($driftTone['value']); ?>"><?php echo e($latestDriftLabel); ?></p>
            <p class="mt-1 text-xs text-slate-400"><?php echo e($latestDriftWhen); ?></p>
            <p class="mt-3 text-sm leading-6 text-slate-300"><?php echo e($latestDriftSummary); ?></p>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/github-sync-drift-card.blade.php ENDPATH**/ ?>