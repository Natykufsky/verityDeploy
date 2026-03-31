<div class="deployment-frost-card rounded-3xl p-5">
    @php($snapshot = $record->page_snapshot)

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                Deployment snapshot
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">
                    {{ $snapshot['headline'] }}
                </h2>
                <p class="max-w-3xl text-sm leading-7 text-slate-300 md:text-base">
                    {{ $snapshot['summary'] }}
                </p>
            </div>

            <div class="deployment-frost-panel grid gap-2 rounded-2xl p-4 text-sm text-slate-300">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Elapsed</span>
                    <span class="font-semibold text-white">{{ $snapshot['timing']['elapsed'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Started</span>
                    <span class="font-medium text-slate-200">{{ $snapshot['timing']['started'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Finished</span>
                    <span class="font-medium text-slate-200">{{ $snapshot['timing']['finished'] }}</span>
                </div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 via-cyan-400 to-sky-400" style="width: {{ $snapshot['progress']['percentage'] }}%"></div>
                </div>
                <div class="flex items-center justify-between gap-4 text-xs text-slate-400">
                    <span>{{ $snapshot['progress']['summary'] }}</span>
                    <span>{{ $snapshot['progress']['percentage'] }}%</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @foreach ($snapshot['badges'] as $badge)
                <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ match ($badge['color']) {
                    'success' => 'bg-emerald-500/15 text-emerald-300',
                    'warning' => 'bg-amber-500/15 text-amber-300',
                    'danger' => 'bg-rose-500/15 text-rose-300',
                    'info' => 'bg-sky-500/15 text-sky-300',
                    'primary' => 'bg-cyan-500/15 text-cyan-300',
                    default => 'bg-white/5 text-slate-300',
                } }}">
                    {{ $badge['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-3">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Next action</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $snapshot['next_action'] }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $snapshot['next_action_description'] }}</p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Release</div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->release_path ?? 'Pending release path' }}</div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ filled($record->archive_uploaded_at) ? 'Archive upload completed and can be reused on resume.' : 'Archive upload will happen the first time the deployment reaches the transport step.' }}
            </p>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Recovery</div>
            <div class="mt-2 text-sm font-semibold text-white">
                {{ filled($record->recovery_hint) ? 'Guidance is available' : 'No recovery hint yet' }}
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                {{ filled($record->recovery_hint) ? $record->recovery_hint : 'If the deployment fails, a recovery hint will explain what to fix and which action to take next.' }}
            </p>
        </div>
    </div>
</div>
