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
@endphp

<div class="space-y-5" wire:poll.15s>
    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-white/5 bg-slate-950/70 p-4">
            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Unread</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['unread'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-white/5 bg-slate-950/70 p-4">
            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Critical</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['critical'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-white/5 bg-slate-950/70 p-4">
            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Last 24 hours</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['recent'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-white/5 bg-slate-950/70 p-4">
            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['total'] ?? 0 }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ($filters as $key => $label)
            <button
                type="button"
                wire:click="setFilter('{{ $key }}')"
                class="rounded-full px-3 py-2 text-xs font-semibold transition
                    {{ $filter === $key
                        ? 'bg-white text-slate-950'
                        : 'border border-white/10 bg-white/5 text-slate-200 hover:bg-white/10' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="max-h-[58vh] overflow-y-auto pr-1">
        @if ($notifications->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-6 text-sm text-slate-400 dark:border-white/10">
                No alerts match the current filter.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($notifications as $notification)
                    @php
                        $tone = $this->notificationTone($notification);
                        $context = $this->notificationContext($notification);
                        $isUnread = is_null($notification->read_at);
                    @endphp
                    <article
                        class="rounded-2xl border border-white/5 bg-slate-950/70 p-4 shadow-sm {{ $toneStyles[$tone]['border'] }}"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full {{ $toneStyles[$tone]['dot'] }}"></span>
                                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Alert</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] {{ $isUnread ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300' }}">
                                        {{ $isUnread ? 'Unread' : 'Read' }}
                                    </span>
                                </div>

                                <h3 class="text-base font-semibold text-white">{{ $this->notificationTitle($notification) }}</h3>
                                <p class="max-w-3xl text-sm leading-6 text-slate-300">{{ $this->notificationBody($notification) }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if ($this->notificationUrl($notification))
                                    <button
                                        type="button"
                                        wire:click="openNotification('{{ $notification->id }}')"
                                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                                    >
                                        View
                                    </button>
                                @endif

                                @if ($isUnread)
                                    <button
                                        type="button"
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                                    >
                                        Mark read
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="markAsUnread('{{ $notification->id }}')"
                                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                                    >
                                        Mark unread
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="dismiss('{{ $notification->id }}')"
                                    class="rounded-full border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                >
                                    Dismiss
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Level</p>
                                <p class="mt-1 text-sm font-semibold {{ $toneStyles[$tone]['value'] }}">{{ data_get($notification->data, 'level', 'warning') }}</p>
                            </div>
                            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">When</p>
                                <p class="mt-1 text-sm font-semibold text-slate-100">{{ $this->notificationWhen($notification) }}</p>
                            </div>
                            <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Context</p>
                                <p class="mt-1 text-sm font-semibold text-slate-100">
                                    {{ filled($context) ? collect($context)->keys()->join(', ') : 'No extra context' }}
                                </p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @php($activeNotification = $this->activeNotification())
    @php($activeIndex = filled($activeNotification) ? array_search($activeNotification->id, $notificationIds, true) : false)

    @if ($activeNotification)
        <div
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur-sm"
            wire:click.self="closeNotification"
            x-data
            x-on:keydown.window.arrow-left.prevent="$wire.previousNotification()"
            x-on:keydown.window.arrow-right.prevent="$wire.nextNotification()"
            x-on:keydown.window.escape.prevent="$wire.closeNotification()"
            tabindex="0"
        >
            <div class="relative mx-auto flex w-full max-w-3xl max-h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl shadow-black/50">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(251,191,36,0.16),transparent_32%),radial-gradient(circle_at_top_right,rgba(56,189,248,0.12),transparent_28%),linear-gradient(180deg,rgba(255,255,255,0.04),rgba(255,255,255,0.01))]"></div>
                <div class="pointer-events-none absolute inset-0 backdrop-blur-[2px]"></div>

                <div class="relative z-10 flex flex-wrap items-start justify-between gap-3 border-b border-white/5 px-5 py-4">
                    <div class="space-y-1">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Alert details</div>
                        <h3 class="text-lg font-semibold text-white">{{ $this->notificationTitle($activeNotification) }}</h3>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="previousNotification"
                            @disabled($activeIndex === false || $activeIndex <= 0)
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Previous
                        </button>

                        <button
                            type="button"
                            wire:click="nextNotification"
                            @disabled($activeIndex === false || $activeIndex >= count($notificationIds) - 1)
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Next
                        </button>

                        @if ($this->notificationUrl($activeNotification))
                            <a
                                href="{{ $this->notificationUrl($activeNotification) }}"
                                class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                            >
                                Open linked record
                            </a>
                        @endif

                        <button
                            type="button"
                            wire:click="closeNotification"
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div class="relative z-10 flex-1 overflow-y-auto px-5 py-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/5 bg-black/20 p-3">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Level</p>
                            <p class="mt-1 text-sm font-semibold {{ $toneStyles[$this->notificationTone($activeNotification)]['value'] }}">
                                {{ data_get($activeNotification->data, 'level', 'warning') }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-white/5 bg-black/20 p-3">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">When</p>
                            <p class="mt-1 text-sm font-semibold text-slate-100">{{ $this->notificationWhen($activeNotification) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/5 bg-black/20 p-3">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">State</p>
                            <p class="mt-1 text-sm font-semibold text-slate-100">
                                {{ $activeNotification->read_at ? 'Read' : 'Unread' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-white/5 bg-black/20 p-4">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Message</p>
                        <p class="mt-2 text-sm leading-7 text-slate-200">{{ $this->notificationBody($activeNotification) }}</p>
                    </div>

                    <div class="mt-4 rounded-2xl border border-white/5 bg-black/20 p-4">
                        <p class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Context</p>
                        @php($context = $this->notificationContext($activeNotification))

                        @if (filled($context))
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($context as $key => $value)
                                    <div class="rounded-xl border border-white/5 bg-slate-950/70 p-3">
                                        <dt class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $key }}</dt>
                                        <dd class="mt-1 break-words text-sm text-slate-100">
                                            {{ is_scalar($value) || is_null($value) ? (string) $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <p class="mt-2 text-sm text-slate-400">No extra context was stored with this alert.</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($activeNotification->read_at)
                            <button
                                type="button"
                                wire:click="markAsUnread('{{ $activeNotification->id }}')"
                                class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                            >
                                Mark unread
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="markAsRead('{{ $activeNotification->id }}')"
                                class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-white/10"
                            >
                                Mark read
                            </button>
                        @endif

                        <button
                            type="button"
                            wire:click="dismiss('{{ $activeNotification->id }}')"
                            class="rounded-full border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
