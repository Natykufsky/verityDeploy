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

    $backupTone = $toneStyles[$latestBackupTone] ?? $toneStyles['slate'];

    $cards = [
        [
            'label' => 'Successful',
            'value' => $successfulBackups,
            'tone' => 'emerald',
        ],
        [
            'label' => 'Failed',
            'value' => $failedBackups,
            'tone' => 'rose',
        ],
        [
            'label' => 'Running',
            'value' => $runningBackups,
            'tone' => 'amber',
        ],
        [
            'label' => 'Total',
            'value' => $totalBackups,
            'tone' => 'slate',
        ],
    ];
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Backups</p>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        <?php echo e($totalBackups); ?> backups
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Backup status at a glance</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    See how many backups have completed, review the latest snapshot, and jump straight to the affected site.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="<?php echo e($sitesIndexUrl); ?>"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open sites
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($latestBackupUrl)): ?>
                    <a
                        href="<?php echo e($latestBackupUrl); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Latest backup
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500"><?php echo e($card['label']); ?></p>
                    <p class="mt-1 text-2xl font-semibold <?php echo e($toneStyles[$card['tone']]['value']); ?>"><?php echo e($card['value']); ?></p>
                    <p class="mt-1 text-xs text-slate-400">Backup runs</p>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 <?php echo e($backupTone['border']); ?>">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full <?php echo e($backupTone['dot']); ?>"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest backup</p>
            </div>
            <p class="mt-1 text-sm font-semibold <?php echo e($backupTone['value']); ?>"><?php echo e($latestBackupLabel); ?></p>
            <p class="mt-1 text-xs text-slate-400"><?php echo e($latestBackupWhen); ?></p>
            <p class="mt-3 text-sm leading-6 text-slate-300"><?php echo e($latestBackupSummary); ?></p>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/site-backup-overview-card.blade.php ENDPATH**/ ?>