<div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
    <button
        type="button"
        wire:click="mountAction('createBackup')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-200">New</span>
        <span class="text-sm font-semibold text-white">Create backup</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">Copy the current release into the backups directory.</span>
    </button>

    <button
        type="button"
        wire:click="mountAction('restoreBackup')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-cyan-400/20 bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-cyan-200">Restore</span>
        <span class="text-sm font-semibold text-white">Restore backup</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">Choose a backup snapshot and restore it as the current release.</span>
    </button>

    <button
        type="button"
        wire:click="mountAction('cleanupReleases')"
        class="deployment-frost-panel flex flex-col rounded-2xl p-4 text-left transition hover:scale-[1.01]"
    >
        <span class="inline-flex w-fit items-center rounded-full border border-amber-400/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-amber-200">Rotate</span>
        <span class="text-sm font-semibold text-white">Clean releases</span>
        <span class="mt-1 text-xs leading-5 text-slate-400">Rotate old release folders and keep the latest five.</span>
    </button>
</div>
