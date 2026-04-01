@php
    $statusColor = match ($overallState) {
        'Healthy' => 'emerald',
        'Needs attention' => 'rose',
        'In progress' => 'amber',
        default => 'slate',
    };

    $badgeColor = match ($statusColor) {
        'emerald' => 'bg-emerald-500/15 text-emerald-300',
        'rose' => 'bg-rose-500/15 text-rose-300',
        'amber' => 'bg-amber-500/15 text-amber-300',
        default => 'bg-slate-500/15 text-slate-300',
    };

    $rowColor = match ($statusColor) {
        'emerald' => 'ring-emerald-500/20 hover:border-emerald-400/30 hover:bg-emerald-500/5',
        'rose' => 'ring-rose-500/20 hover:border-rose-400/30 hover:bg-rose-500/5',
        'amber' => 'ring-amber-500/20 hover:border-amber-400/30 hover:bg-amber-500/5',
        default => 'ring-slate-500/20 hover:border-slate-400/30 hover:bg-slate-500/5',
    };

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

    $canOpenLatest = filled($latestRunUrl);
@endphp

<div class="space-y-4" wire:poll.30s>
    <div
        @if ($canOpenLatest)
            wire:click="openLatestRun"
        @endif
        class="group rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm transition
            {{ $canOpenLatest ? 'cursor-pointer hover:border-slate-100/20 hover:bg-slate-900/80' : '' }}"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">cPanel setup</p>
                        <x-info-tooltip text="The latest cPanel setup snapshot for server and site bootstrap runs." label="cPanel setup help" />
                    </div>
                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ $badgeColor }}">
                        {{ $overallState }}
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Latest setup snapshot</h3>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click.stop="openServerRun"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Server wizard
                </button>
                <button
                    type="button"
                    wire:click.stop="openSiteRun"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Site wizard
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-black/20 p-3 {{ $toneStyles[$serverRunTone]['border'] }}">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $toneStyles[$serverRunTone]['dot'] }}"></span>
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        <span>Server run</span>
                        <x-info-tooltip text="The most recent cPanel wizard run for server checks." label="Server run help" />
                    </div>
                </div>
                <p class="mt-1 text-sm font-semibold {{ $toneStyles[$serverRunTone]['value'] }}">{{ $serverRunBadge ?? 'No run' }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $serverRunWhen }}</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 {{ $toneStyles[$siteRunTone]['border'] }}">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $toneStyles[$siteRunTone]['dot'] }}"></span>
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        <span>Site run</span>
                        <x-info-tooltip text="The most recent cPanel wizard run for site bootstrap." label="Site run help" />
                    </div>
                </div>
                <p class="mt-1 text-sm font-semibold {{ $toneStyles[$siteRunTone]['value'] }}">{{ $siteRunBadge ?? 'No run' }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $siteRunWhen }}</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 {{ $toneStyles[$auditCountTone]['border'] }}">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $toneStyles[$auditCountTone]['dot'] }}"></span>
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        <span>Last 24 hours</span>
                        <x-info-tooltip text="How many cPanel wizard audit records were created in the last day." label="Last 24 hours help" />
                    </div>
                </div>
                <p class="mt-1 text-sm font-semibold {{ $toneStyles[$auditCountTone]['value'] }}">{{ $auditCountLast24Hours ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-400">Audit records</p>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-white/5 bg-black/20 p-4 transition {{ $rowColor }}">
                <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                    <span>Server wizard</span>
                    <x-info-tooltip text="Jump to the cPanel checks run for the server." label="Server wizard help" />
                </div>
                <p class="mt-1 text-base font-semibold text-white">{{ $serverRunLabel ?? 'No run yet' }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <span>Status</span>
                            <x-info-tooltip text="The current state of the server wizard run." label="Status help" />
                        </div>
                        <p class="mt-1 font-medium text-slate-100">{{ $serverRunState }}</p>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <span>Updated</span>
                            <x-info-tooltip text="When the server wizard run last changed state." label="Updated help" />
                        </div>
                        <p class="mt-1 font-medium text-slate-100">{{ $serverRunWhen }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-4 transition {{ $rowColor }}">
                <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                    <span>Site wizard</span>
                    <x-info-tooltip text="Jump to the cPanel bootstrap run for the site." label="Site wizard help" />
                </div>
                <p class="mt-1 text-base font-semibold text-white">{{ $siteRunLabel ?? 'No run yet' }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <span>Status</span>
                            <x-info-tooltip text="The current state of the site bootstrap run." label="Status help" />
                        </div>
                        <p class="mt-1 font-medium text-slate-100">{{ $siteRunState }}</p>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <span>Updated</span>
                            <x-info-tooltip text="When the site wizard run last changed state." label="Updated help" />
                        </div>
                        <p class="mt-1 font-medium text-slate-100">{{ $siteRunWhen }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-white/5 pt-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                Click the card to open {{ $latestRunLabel }}.
            </div>

            @if ($canOpenLatest)
                <div class="text-xs font-medium text-slate-300">
                    Latest target is ready
                </div>
            @endif
        </div>
    </div>
</div>
