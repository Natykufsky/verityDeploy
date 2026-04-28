<div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
    <button
        type="button"
        wire:click="mountAction('createBackup')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-200">new</span>
        <span class="text-sm font-semibold text-white">create backup</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">copy the current release into the backups directory.</span>
    </button>

    <button
        type="button"
        wire:click="mountAction('restoreBackup')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-cyan-400/20 bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-cyan-200">restore</span>
        <span class="text-sm font-semibold text-white">restore backup</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">choose a backup snapshot and restore it as the current release.</span>
    </button>

    <button
        type="button"
        wire:click="mountAction('verifyLatestBackup')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-sky-400/20 bg-sky-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-sky-200">verify</span>
        <span class="text-sm font-semibold text-white">verify latest backup</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">check the latest snapshot against its recorded checksum and file presence.</span>
    </button>

    <button
        type="button"
        wire:click="mountAction('cleanupReleases')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-amber-400/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-amber-200">rotate</span>
        <span class="text-sm font-semibold text-white">clean releases</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">rotate old release folders and keep the latest five.</span>
    </button>
</div>
