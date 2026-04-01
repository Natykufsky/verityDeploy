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

    $checkTone = $toneStyles[$latestCheckTone] ?? $toneStyles['slate'];

    $cards = [
        [
            'label' => 'Online',
            'value' => $onlineCount,
            'tone' => 'emerald',
        ],
        [
            'label' => 'Offline',
            'value' => $offlineCount,
            'tone' => 'slate',
        ],
        [
            'label' => 'Error',
            'value' => $errorCount,
            'tone' => 'rose',
        ],
        [
            'label' => 'Total',
            'value' => $totalCount,
            'tone' => 'amber',
        ],
    ];
@endphp

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Server health</p>
                        <x-info-tooltip text="A snapshot of server availability and the latest health check result." label="Server health help" />
                    </div>
                    <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-200">
                        {{ $onlineCount }} online
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white">Server health at a glance</h3>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ $serverIndexUrl }}"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open servers
                </a>

                @if (filled($latestCheckUrl))
                    <a
                        href="{{ $latestCheckUrl }}"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Latest check
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            @foreach ($cards as $card)
                <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        <span>{{ $card['label'] }}</span>
                        <x-info-tooltip text="Dashboard count for {{ strtolower($card['label']) }} servers." label="{{ $card['label'] }} help" />
                    </div>
                    <p class="mt-1 text-2xl font-semibold {{ $toneStyles[$card['tone']]['value'] }}">{{ $card['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">servers</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-xl border bg-black/20 p-4 {{ $checkTone['border'] }}">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full {{ $checkTone['dot'] }}"></span>
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Latest health check</p>
                <x-info-tooltip text="The most recent server health check and its summarized metrics." label="Latest health check help" />
            </div>
            <p class="mt-1 text-sm font-semibold {{ $checkTone['value'] }}">{{ $latestCheckLabel }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $latestCheckWhen }}</p>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $latestCheckSummary }}</p>
        </div>
    </div>
</div>
