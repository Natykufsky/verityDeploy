@php
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

    $backupTone = $toneStyles[$latestBackupTone] ?? $toneStyles['slate'];

    $cards = [
        [
            'label' => 'Successful',
            'value' => $successfulBackups,
            'tone' => 'emerald',
        ],
        [
            'label' => 'Failed',
            'value' => $failedBackups,
            'tone' => 'rose',
        ],
        [
            'label' => 'Running',
            'value' => $runningBackups,
            'tone' => 'amber',
        ],
        [
            'label' => 'Total',
            'value' => $totalBackups,
            'tone' => 'slate',
        ],
    ];
@endphp

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Backups</p>
                        <x-info-tooltip text="A snapshot of backup activity and the latest backup status." label="Backups help" />
                    </div>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                        {{ $totalBackups }} backups
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Backup status at a glance</h3>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ $sitesIndexUrl }}"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open sites
                </a>

                @if (filled($latestBackupUrl))
                    <a
                        href="{{ $latestBackupUrl }}"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Latest backup
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            @foreach ($cards as $card)
                <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        <span>{{ $card['label'] }}</span>
                        <x-info-tooltip text="Backup count for {{ strtolower($card['label']) }} runs." label="{{ $card['label'] }} help" />
                    </div>
                    <p class="mt-1 text-2xl font-semibold {{ $toneStyles[$card['tone']]['value'] }}">{{ $card['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">backup runs</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 {{ $backupTone['border'] }}">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full {{ $backupTone['dot'] }}"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest backup</p>
                <x-info-tooltip text="The latest backup run and its summarized state." label="Latest backup help" />
            </div>
            <p class="mt-1 text-sm font-semibold {{ $backupTone['value'] }}">{{ $latestBackupLabel }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $latestBackupWhen }}</p>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $latestBackupSummary }}</p>
        </div>
    </div>
</div>
