<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Status</div>
        <div class="mt-2 text-sm font-semibold text-white">{{ $record->backup_status_badge }}</div>
        <p class="mt-1 text-sm text-slate-400">{{ $record->backup_status }}</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Latest backup</div>
        <div class="mt-2 text-sm font-semibold text-white">{{ $record->latest_backup_summary }}</div>
        <p class="mt-1 text-sm text-slate-400">Most recent successful snapshot.</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Latest snapshot path</div>
        <div class="mt-2 break-all font-mono text-xs text-slate-100">{{ $record->latest_backup_snapshot_path ?? 'No snapshot recorded yet.' }}</div>
    </div>
</div>
