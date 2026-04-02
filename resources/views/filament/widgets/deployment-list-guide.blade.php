<div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 text-sm leading-7 text-slate-300 dark:border-white/10">
    <div class="flex items-center gap-2 mb-3">
        <p class="font-semibold text-white">Deployment Management Guide</p>
        <x-info-tooltip text="Manage your global deployment history and active runs." label="Management guide help" />
    </div>
    <p>
        The Deployment list tracks the historical and current state of all release attempts across your sites. Monitor <span class="font-semibold text-white">Triggered By</span>
        to see which user or automated process started a deploy, and use the <span class="font-semibold text-white">Status Badge</span> to filter for failed runs.
        <x-info-tooltip text="Click the View button to see live terminal logs and the detailed step-by-step progress." label="View deploy help" />
    </p>
    <p class="mt-3">
        You can prune <span class="text-rose-300 font-semibold italic">Stale Failures</span> using the top-right action to keep this list clean. Records older than 30 days that are failed 
        are usually considered noise and can be safely removed.
        <x-info-tooltip text="Pruning only deletes the record from the dashboard, it does not affect any files on the servers." label="Pruning help" />
    </p>
</div>
