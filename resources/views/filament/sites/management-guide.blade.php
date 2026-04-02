<div class="space-y-6">
    <div class="flex items-start gap-4">
        <div class="p-2 bg-primary-100 dark:bg-primary-900/50 rounded-lg shrink-0">
            <x-filament::icon icon="heroicon-m-academic-cap" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-bold text-primary-950 dark:text-primary-50">Site Management Quick Guide</h3>
            <p class="text-xs text-primary-800 dark:text-primary-300 leading-relaxed mt-1">
                VerityDeploy uses context-aware header actions to manage the end-to-end lifecycle of your web applications.
            </p>
        </div>
    </div>

    <!-- Main Categories -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-primary-900 dark:text-primary-100 uppercase tracking-widest">
                <x-filament::icon icon="heroicon-m-paper-airplane" class="w-4 h-4 text-emerald-500" />
                Ship
            </div>
            <p class="text-[11px] text-primary-800/80 dark:text-primary-400 leading-relaxed">
                Primary workflow for active sites. Use this to <strong>Deploy now</strong> from your source or access the <strong>Remote Terminal</strong> for live commands.
            </p>
        </div>
        
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-primary-900 dark:text-primary-100 uppercase tracking-widest">
                <x-filament::icon icon="heroicon-m-bolt" class="w-4 h-4 text-amber-500" />
                Provisioning
            </div>
            <p class="text-[11px] text-primary-800/80 dark:text-primary-400 leading-relaxed">
                Infrastructure setup. Initialize <strong>Deployment Paths</strong>, configure <strong>Domains</strong>, and generate <strong>SSL certificates</strong> on the fly.
            </p>
        </div>
        
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-primary-900 dark:text-primary-100 uppercase tracking-widest">
                <x-filament::icon icon="heroicon-m-beaker" class="w-4 h-4 text-cyan-500" />
                Diagnostics
            </div>
            <p class="text-[11px] text-primary-800/80 dark:text-primary-400 leading-relaxed">
                Maintenance and health checks. Sync <strong>Live Inventory</strong> to detect <strong>Configuration Drift</strong> between the dashboard and the actual server.
            </p>
        </div>

        <div class="space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-primary-900 dark:text-primary-100 uppercase tracking-widest">
                <x-filament::icon icon="heroicon-m-ellipsis-horizontal-circle" class="w-4 h-4 text-slate-500" />
                More
            </div>
            <p class="text-[11px] text-primary-800/80 dark:text-primary-400 leading-relaxed">
                Utility drawer for secondary management tasks, legacy wizards, and deep-level maintenance routines.
            </p>
        </div>
    </div>

    <!-- More Details Breakdown -->
    <div class="p-5 bg-slate-100/50 dark:bg-black/20 border border-slate-200 dark:border-white/5 rounded-2xl">
        <h4 class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-[0.2em] mb-4">Inside the "More" Menu</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-4 gap-x-8">
            <div class="space-y-1">
                <div class="text-[11px] font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span> Maintenance
                </div>
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <strong>Clean Releases:</strong> Purge all but the latest 5 deployments to free up disk space.
                </p>
            </div>
            <div class="space-y-1">
                <div class="text-[11px] font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Recovery
                </div>
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <strong>Backups (Long-term):</strong> Capture or restore release-independent site snapshots.
                </p>
            </div>
            <div class="space-y-1">
                <div class="text-[11px] font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-500"></span> Automations
                </div>
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <strong>Webhooks:</strong> Refresh or re-provision the GitHub push deployment connection status.
                </p>
            </div>
            <div class="space-y-1">
                <div class="text-[11px] font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Rollbacks
                </div>
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <strong>Restore Release:</strong> Immediately switch the live symlink back to a known-good prior deployment.
                </p>
            </div>
            <div class="space-y-1">
                <div class="text-[11px] font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-400"></span> Wizards
                </div>
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <strong>cPanel Setup:</strong> Start the guided bootstrap process for fresh cPanel accounts.
                </p>
            </div>
        </div>
    </div>
</div>
