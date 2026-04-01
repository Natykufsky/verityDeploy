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

    $latestAlertToneStyle = $toneStyles[$latestAlertTone] ?? $toneStyles['slate'];
@endphp

<div class="space-y-4" wire:poll.30s>
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-200">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="h-6 w-6" />
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Alerts inbox</p>
                            <x-info-tooltip text="A compact snapshot of unread and critical alerts from the dashboard." label="Alerts inbox help" />
                        </div>
                        <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-200">
                            {{ $unreadCount }} unread
                        </span>
                    </div>

                    <h3 class="text-lg font-semibold text-white">Keep an eye on operational alerts</h3>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ $inboxUrl }}"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                >
                    Open inbox
                </a>

                @if ($hasUnread)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                    <span>Unread</span>
                    <x-info-tooltip text="Alerts that still need to be reviewed." label="Unread help" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-white">{{ $unreadCount }}</p>
                <p class="mt-1 text-xs text-slate-400">alerts waiting for review</p>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.22em] text-slate-500">
                    <span>Critical</span>
                    <x-info-tooltip text="High-priority alerts that need attention first." label="Critical help" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-white">{{ $criticalCount }}</p>
                <p class="mt-1 text-xs text-slate-400">warning and danger alerts</p>
            </div>

            <div class="rounded-xl border bg-black/20 p-3 {{ $latestAlertToneStyle['border'] }}">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $latestAlertToneStyle['dot'] }}"></span>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Latest</p>
                    <x-info-tooltip text="The most recent alert and its current state." label="Latest alert help" />
                </div>
                <p class="mt-1 text-sm font-semibold {{ $latestAlertToneStyle['value'] }}">{{ $latestAlertTitle }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $latestAlertWhen }}</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-white/5 bg-black/20 p-4">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.24em] text-slate-500">
                <span>Latest alert</span>
                <x-info-tooltip text="A short preview of the most recent alert body." label="Latest alert help" />
            </div>
            <p class="mt-1 text-sm font-semibold text-white">{{ $latestAlertTitle }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $latestAlertBody }}</p>
        </div>
    </div>
</div>
