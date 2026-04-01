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

    $latestAlertToneStyle = $toneStyles[$latestAlertTone] ?? $toneStyles['slate'];
?>

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-200">
                    <?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-o-bell-alert','class' => 'h-6 w-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-bell-alert','class' => 'h-6 w-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Alerts inbox</p>
                        <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                            <?php echo e($unreadCount); ?> unread
                        </span>
                    </div>

                    <h3 class="text-lg font-semibold text-white">Keep an eye on operational alerts</h3>
                    <p class="max-w-2xl text-sm leading-6 text-slate-400">
                        Review failed deploys, unhealthy servers, and webhook drift from the dashboard before they turn into surprises.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="<?php echo e($inboxUrl); ?>"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open inbox
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasUnread): ?>
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Mark all read
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Unread</p>
                <p class="mt-1 text-2xl font-semibold text-white"><?php echo e($unreadCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Alerts waiting for review</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Critical</p>
                <p class="mt-1 text-2xl font-semibold text-white"><?php echo e($criticalCount); ?></p>
                <p class="mt-1 text-xs text-slate-400">Warning and danger alerts</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 <?php echo e($latestAlertToneStyle['border']); ?>">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?php echo e($latestAlertToneStyle['dot']); ?>"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Latest</p>
                </div>
                <p class="mt-1 text-sm font-semibold <?php echo e($latestAlertToneStyle['value']); ?>"><?php echo e($latestAlertTitle); ?></p>
                <p class="mt-1 text-xs text-slate-400"><?php echo e($latestAlertWhen); ?></p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-white/5 bg-black/20 p-4">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest alert</p>
            <p class="mt-1 text-sm font-semibold text-white"><?php echo e($latestAlertTitle); ?></p>
            <p class="mt-2 text-sm leading-6 text-slate-400"><?php echo e($latestAlertBody); ?></p>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/widgets/alerts-inbox-widget.blade.php ENDPATH**/ ?>