<?php
    $statusColor = match ($overallState) {
        'Healthy' => 'emerald',
        'Needs attention' => 'rose',
        'In progress' => 'amber',
        default => 'slate',
    };

    $badgeColor = match ($statusColor) {
        'emerald' => 'bg-emerald-500/15 text-emerald-300',
        'rose' => 'bg-rose-500/15 text-rose-300',
        'amber' => 'bg-amber-500/15 text-amber-300',
        default => 'bg-slate-500/15 text-slate-300',
    };

    $rowColor = match ($statusColor) {
        'emerald' => 'ring-emerald-500/20 hover:border-emerald-400/30 hover:bg-emerald-500/5',
        'rose' => 'ring-rose-500/20 hover:border-rose-400/30 hover:bg-rose-500/5',
        'amber' => 'ring-amber-500/20 hover:border-amber-400/30 hover:bg-amber-500/5',
        default => 'ring-slate-500/20 hover:border-slate-400/30 hover:bg-slate-500/5',
    };

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

    $canOpenLatest = filled($latestRunUrl);
?>

<div class="space-y-4" wire:poll.30s>
    <div
        <?php if($canOpenLatest): ?>
            wire:click="openLatestRun"
        <?php endif; ?>
        class="group rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm transition
            <?php echo e($canOpenLatest ? 'cursor-pointer hover:border-slate-100/20 hover:bg-slate-900/80' : ''); ?>"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs uppercase tracking-[0.28em] text-slate-400">cPanel setup</p>
                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] <?php echo e($badgeColor); ?>">
                        <?php echo e($overallState); ?>

                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Latest setup snapshot</h3>
                <p class="max-w-2xl text-sm leading-6 text-slate-400">
                    Open the latest wizard run or jump directly to the server or site setup record.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click.stop="openServerRun"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Server wizard
                </button>
                <button
                    type="button"
                    wire:click.stop="openSiteRun"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Site wizard
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-black/20 p-3 <?php echo e($toneStyles[$serverRunTone]['border']); ?>">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?php echo e($toneStyles[$serverRunTone]['dot']); ?>"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Server run</p>
                </div>
                <p class="mt-1 text-sm font-semibold <?php echo e($toneStyles[$serverRunTone]['value']); ?>"><?php echo e($serverRunBadge ?? 'No run'); ?></p>
                <p class="mt-1 text-xs text-slate-400"><?php echo e($serverRunWhen); ?></p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 <?php echo e($toneStyles[$siteRunTone]['border']); ?>">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?php echo e($toneStyles[$siteRunTone]['dot']); ?>"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Site run</p>
                </div>
                <p class="mt-1 text-sm font-semibold <?php echo e($toneStyles[$siteRunTone]['value']); ?>"><?php echo e($siteRunBadge ?? 'No run'); ?></p>
                <p class="mt-1 text-xs text-slate-400"><?php echo e($siteRunWhen); ?></p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 <?php echo e($toneStyles[$auditCountTone]['border']); ?>">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?php echo e($toneStyles[$auditCountTone]['dot']); ?>"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Last 24 hours</p>
                </div>
                <p class="mt-1 text-sm font-semibold <?php echo e($toneStyles[$auditCountTone]['value']); ?>"><?php echo e($auditCountLast24Hours ?? 0); ?></p>
                <p class="mt-1 text-xs text-slate-400">Audit records</p>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-white/5 bg-black/20 p-4 transition <?php echo e($rowColor); ?>">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Server wizard</p>
                <p class="mt-1 text-base font-semibold text-white"><?php echo e($serverRunLabel ?? 'No run yet'); ?></p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status</p>
                        <p class="mt-1 font-medium text-slate-100"><?php echo e($serverRunState); ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Updated</p>
                        <p class="mt-1 font-medium text-slate-100"><?php echo e($serverRunWhen); ?></p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-4 transition <?php echo e($rowColor); ?>">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Site wizard</p>
                <p class="mt-1 text-base font-semibold text-white"><?php echo e($siteRunLabel ?? 'No run yet'); ?></p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status</p>
                        <p class="mt-1 font-medium text-slate-100"><?php echo e($siteRunState); ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Updated</p>
                        <p class="mt-1 font-medium text-slate-100"><?php echo e($siteRunWhen); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-white/5 pt-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                Click the card to open <?php echo e($latestRunLabel); ?>.
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canOpenLatest): ?>
                <div class="text-xs font-medium text-slate-300">
                    Latest target is ready
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/cpanel-setup-card.blade.php ENDPATH**/ ?>