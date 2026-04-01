<?php
    $statusColor = match ($record->status) {
        'online' => 'success',
        'offline' => 'gray',
        'error' => 'danger',
        default => 'warning',
    };

    $connectionColor = match ($record->connection_type) {
        'ssh_key' => 'success',
        'password' => 'warning',
        'local' => 'gray',
        'cpanel' => 'info',
        default => 'gray',
    };

    $nextAction = match (true) {
        $record->connection_type === 'cpanel' && blank($record->cpanel_api_token) => 'Add the cPanel API token and test the API connection.',
        $record->connection_type === 'password' && blank($record->sudo_password) => 'Add the SSH password before testing the connection.',
        $record->connection_type === 'ssh_key' && blank($record->ssh_key) => 'Generate or paste the SSH key before running the next check.',
        default => 'Open the terminal or run a connection test to verify the server.',
    };
?>

<details open class="deployment-frost-card rounded-3xl p-5">
    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                Server overview
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl"><?php echo e($record->name); ?></h2>
                <p class="max-w-3xl text-sm leading-7 text-slate-300 md:text-base">
                    <?php echo e($record->provider_summary); ?>

                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] <?php echo e($statusColor === 'success' ? 'bg-emerald-500/15 text-emerald-300' : ($statusColor === 'danger' ? 'bg-rose-500/15 text-rose-300' : ($statusColor === 'warning' ? 'bg-amber-500/15 text-amber-300' : 'bg-white/5 text-slate-300'))); ?>">
                <?php echo e($record->status); ?>

            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] <?php echo e($connectionColor === 'success' ? 'bg-emerald-500/15 text-emerald-300' : ($connectionColor === 'danger' ? 'bg-rose-500/15 text-rose-300' : ($connectionColor === 'warning' ? 'bg-amber-500/15 text-amber-300' : ($connectionColor === 'info' ? 'bg-sky-500/15 text-sky-300' : 'bg-white/5 text-slate-300')))); ?>">
                <?php echo e($record->connection_type); ?>

            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                <?php echo e($record->provider_label); ?>

            </span>
        </div>
    </summary>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Owner</div>
            <div class="mt-2 text-sm font-semibold text-white"><?php echo e($record->owner?->name ?? 'Unassigned'); ?></div>
            <p class="mt-1 text-sm text-slate-400"><?php echo e($record->team?->name ?? 'No team assigned'); ?></p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Connection</div>
            <div class="mt-2 text-sm font-semibold text-white"><?php echo e(ucfirst((string) $record->connection_type)); ?></div>
            <p class="mt-1 text-sm text-slate-400">SSH user: <?php echo e($record->ssh_user ?? 'n/a'); ?></p>
            <p class="mt-2 text-sm leading-6 text-slate-300"><?php echo e($record->capability_summary); ?></p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Last connected</div>
            <div class="mt-2 text-sm font-semibold text-white"><?php echo e($record->last_connected_at?->format('M d, Y H:i') ?? 'Never'); ?></div>
            <p class="mt-1 text-sm text-slate-400"><?php echo e($record->connectionTests()->count()); ?> connection checks recorded</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Next action</div>
            <div class="mt-2 text-sm font-semibold text-white"><?php echo e($nextAction); ?></div>
            <p class="mt-1 text-sm text-slate-400"><?php echo e($record->deployments()->count()); ?> deployments across <?php echo e($record->sites()->count()); ?> sites</p>
        </div>
    </div>
</details>
<?php /**PATH C:\Users\Natykufsky\Desktop\Apps\php\verityDeploy\resources\views/filament/servers/server-overview.blade.php ENDPATH**/ ?>