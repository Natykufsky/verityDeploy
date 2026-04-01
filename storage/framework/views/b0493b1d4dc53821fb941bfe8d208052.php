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

    $checkTone = $toneStyles[$latestCheckTone] ?? $toneStyles['slate'];

    $cards = [
        [
            'label' => 'Online',
            'value' => $onlineCount,
            'tone' => 'emerald',
        ],
        [
            'label' => 'Offline',
            'value' => $offlineCount,
            'tone' => 'slate',
        ],
        [
            'label' => 'Error',
            'value' => $errorCount,
            'tone' => 'rose',
        ],
        [
            'label' => 'Total',
            'value' => $totalCount,
            'tone' => 'amber',
        ],
    ];
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Server health</p>
                    <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-200">
                        <?php echo e($onlineCount); ?> online
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Server health at a glance</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    Monitor server status, open the server list, and jump directly to the latest health check when something needs attention.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="<?php echo e($serverIndexUrl); ?>"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open servers
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($latestCheckUrl)): ?>
                    <a
                        href="<?php echo e($latestCheckUrl); ?>"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Latest check
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500"><?php echo e($card['label']); ?></p>
                    <p class="mt-1 text-2xl font-semibold <?php echo e($toneStyles[$card['tone']]['value']); ?>"><?php echo e($card['value']); ?></p>
                    <p class="mt-1 text-xs text-slate-400">Servers</p>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 <?php echo e($checkTone['border']); ?>">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full <?php echo e($checkTone['dot']); ?>"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest health check</p>
            </div>
            <p class="mt-1 text-sm font-semibold <?php echo e($checkTone['value']); ?>"><?php echo e($latestCheckLabel); ?></p>
            <p class="mt-1 text-xs text-slate-400"><?php echo e($latestCheckWhen); ?></p>
            <p class="mt-3 text-sm leading-6 text-slate-300"><?php echo e($latestCheckSummary); ?></p>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/server-health-overview-card.blade.php ENDPATH**/ ?>