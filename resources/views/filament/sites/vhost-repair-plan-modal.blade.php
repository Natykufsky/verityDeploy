@php
    $preview = app(\App\Services\Server\VpsVhostRepairPlanService::class)->preview($record);
    $commands = app(\App\Services\Server\VpsVhostRepairPlanService::class)->commands($record);
@endphp

<div
    class="space-y-4"
    x-data="{
        copied: false,
        copyPlan() {
            const text = this.$refs.planText?.textContent || '';

            if (!text) {
                return;
            }

            navigator.clipboard.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 1500);
            });
        },
    }"
>
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            vps repair plan
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $record->primary_domain ?? 'no primary domain configured yet' }}
            </h3>
            <x-info-tooltip text="This is the read-only plan for aligning the live vhost config with the site intent." label="VPS repair plan help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            {{ $preview['message'] ?? 'This plan shows what would be applied to align the live web server config.' }}
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Paths</span>
                <x-info-tooltip text="These are the exact file paths that would be used for the vhost config." label="Paths help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>vhost file: <span class="font-semibold text-white">{{ $preview['vhost_path'] ?? 'n/a' }}</span></div>
                <div>enabled link: <span class="font-semibold text-white">{{ $preview['enabled_path'] ?? 'n/a' }}</span></div>
                <div>reload: <span class="font-semibold text-white">{{ $preview['reload_command'] ?? 'n/a' }}</span></div>
            </div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Steps</span>
                <x-info-tooltip text="This is the sequence that would be used to repair the live vhost layout." label="Steps help" />
            </div>
            <div class="mt-3 space-y-2">
                @forelse ($preview['steps'] ?? [] as $step)
                    <div class="flex items-start gap-2 text-sm text-slate-300">
                        <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300"></span>
                        <span>{{ $step }}</span>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">No repair steps available.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Commands</span>
                <x-info-tooltip text="These are the commands that would be used to write and activate the vhost config." label="Commands help" />
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10 hover:text-cyan-50"
                x-on:click="copyPlan()"
            >
                <span x-show="!copied">copy repair plan</span>
                <span x-show="copied" x-cloak>copied</span>
            </button>
        </div>
        <div class="mt-3 space-y-3">
            <pre x-ref="planText" class="overflow-x-auto rounded-xl border border-white/5 bg-black/30 p-3 font-mono text-xs leading-6 text-slate-100">{{ implode("\n", $commands) }}</pre>
        </div>
    </div>
</div>
