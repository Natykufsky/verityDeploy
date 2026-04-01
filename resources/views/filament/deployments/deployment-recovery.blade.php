<div class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(16rem,0.7fr)]">
    <div class="deployment-frost-card rounded-2xl p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                Recovery summary
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ match ($record->status) {
                'failed' => 'bg-rose-500/10 text-rose-300',
                'running' => 'bg-amber-500/10 text-amber-300',
                'successful' => 'bg-emerald-500/10 text-emerald-300',
                default => 'bg-slate-500/10 text-slate-300',
            } }}">
                {{ ucfirst($record->status) }}
            </span>
            @if ($record->isResumable())
                <span class="inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-300">
                    Resume ready
                </span>
            @endif
        </div>

        <div class="mt-4 space-y-3">
            <h3 class="text-lg font-semibold text-white">
                {{ $record->page_snapshot['headline'] }}
            </h3>
            <div class="flex items-start gap-2">
                <p class="max-w-3xl text-sm leading-7 text-slate-300">
                    {{ $record->page_snapshot['summary'] }}
                </p>
                <x-info-tooltip text="A compact explanation of what happened and what to do next." label="Recovery summary help" />
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <div class="deployment-frost-panel rounded-xl p-4">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                    <span>Failure summary</span>
                    <x-info-tooltip text="The latest failure message captured for this deployment." label="Failure summary help" />
                </div>
                <div class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-300">
                    <p>{{ filled($record->error_message) ? $record->error_message : 'No failure message is available yet.' }}</p>
                </div>
            </div>

            <div class="deployment-frost-panel rounded-xl p-4">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                    <span>Recovery hint</span>
                    <x-info-tooltip text="The recommended fix or retry guidance for the deployment." label="Recovery hint help" />
                </div>
                <div class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-300">
                    <p>{{ filled($record->recovery_hint) ? $record->recovery_hint : 'A recovery hint will appear once the deployment fails.' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="deployment-frost-card rounded-2xl p-5">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>What to do next</span>
            <x-info-tooltip text="The next steps that should be taken after a failed or resumable deployment." label="What to do next help" />
        </div>
        <div class="mt-3 space-y-3">
            @foreach ($record->page_snapshot['checklist'] as $item)
                <div class="deployment-frost-panel flex items-start gap-3 rounded-xl px-3 py-3 text-sm leading-6 text-slate-300">
                    <span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-[11px] font-bold text-emerald-300">
                        {{ $loop->iteration }}
                    </span>
                    <span>{{ $item }}</span>
                </div>
            @endforeach
        </div>

        <div class="deployment-frost-panel mt-5 rounded-xl p-4 text-sm leading-6 text-slate-400">
            {{ $record->isResumable() ? 'Because some of the deployment already completed, Resume deployment will continue from the next incomplete step and reuse the uploaded archive.' : 'This deployment is not resumable yet. Fix the blocking issue first, then use Retry to queue a fresh attempt.' }}
        </div>
    </div>
</div>
