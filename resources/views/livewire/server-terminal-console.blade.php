@php
    $terminalModalConfig = [
        'componentId' => $this->getId(),
        'feedUrl' => route('servers.terminal-feed', ['record' => $server->id]),
        'promptText' => $terminalPrompt,
        'sessionId' => $terminalSession?->id,
        'sessionOpenUrl' => route('servers.terminal-session.open', ['record' => $server->id]),
        'sessionHeartbeatUrl' => route('servers.terminal-session.heartbeat', ['record' => $server->id]),
        'sessionCloseUrl' => route('servers.terminal-session.close', ['record' => $server->id]),
        'storageKey' => 'verity-server-terminal:' . $server->id,
    ];
@endphp

<div
        data-terminal-config='@json($terminalModalConfig)'
        x-data="verityServerTerminalModal()"
        x-init="initModal(JSON.parse($el.dataset.terminalConfig || '{}'), @entangle('historyFilter').live, @entangle('presetSearch').live, @entangle('presetGroupFilter').live, @entangle('presetTagFilter').live)"
>
    <div class="relative overflow-hidden rounded-3xl border border-cyan-400/20 bg-slate-950/96 shadow-[0_30px_100px_-35px_rgba(0,0,0,.95)] backdrop-blur-xl">
            <div class="flex h-full min-h-0 w-full flex-col">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 px-4 py-3.5 lg:px-5 lg:py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-500/10 text-cyan-100"
                                title="server terminal"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M8 6h1M8 12h1M8 18h1M15 6h1M15 12h1M15 18h1" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                </svg>
                            </button>
                            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-100">
                                Server terminal
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2.5">
                            <h3 class="text-lg font-semibold tracking-tight text-white lg:text-xl">{{ $server->name }}</h3>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">{{ $server->connection_type }}</span>
                            <span class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-100">session #{{ $terminalSession?->id ?? 'new' }}</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">{{ $terminalPrompt }}</span>
                        </div>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                            a draggable xterm.js console. use the sidebar for presets or type commands directly into the terminal.
                        </p>
                    </div>

                    <div class="hidden items-center gap-2 lg:flex">
                        <button type="button" @click="showTerminalTools = !showTerminalTools; persist()" class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span x-show="showTerminalTools" x-cloak>tools: on</span>
                            <span x-show="!showTerminalTools" x-cloak>tools: off</span>
                        </button>
                        <button type="button" @click="resetWorkspace()" class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-amber-100 hover:bg-amber-400/20">
                            reset workspace
                        </button>
                    </div>

                    <details x-ref="mobileActions" class="relative lg:hidden">
                        <summary class="list-none rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span class="inline-flex items-center gap-2">
                                <span>Actions</span>
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] leading-none" :class="showTerminalTools ? 'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' : 'border-slate-500/30 bg-slate-500/10 text-slate-300'" x-text="showTerminalTools ? 'on' : 'off'"></span>
                            </span>
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 w-[18rem] rounded-2xl border border-white/10 bg-slate-950/98 p-2 shadow-[0_20px_60px_-30px_rgba(0,0,0,.95)] backdrop-blur-xl">
                            <div class="grid gap-2">
                                <button type="button" @click="showTerminalTools = !showTerminalTools; persist(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                                    <span x-show="showTerminalTools" x-cloak>tools: on</span>
                                    <span x-show="!showTerminalTools" x-cloak>tools: off</span>
                                </button>
                                <button type="button" @click="resetWorkspace(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-amber-100 hover:bg-amber-400/20">
                                    reset workspace
                                </button>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="grid gap-4 p-4 transition-all duration-300" :class="showTerminalTools ? 'lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]' : 'lg:grid-cols-1'">
                    <aside
                        x-show="showTerminalTools"
                        x-cloak
                        x-transition:enter="transition ease-out duration-220"
                        x-transition:enter-start="opacity-0 -translate-x-3 scale-95"
                        x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                        x-transition:leave="transition ease-in duration-160"
                        x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-x-2 scale-95"
                        class="space-y-4 overflow-hidden lg:max-h-[calc(100vh-16rem)] lg:overflow-y-auto lg:pr-1 lg:origin-left"
                    >
                        <div class="rounded-2xl border border-white/5 bg-black/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">history filter</div>
                                    <p class="mt-1 text-sm text-slate-400">narrow the recent command cards below.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $runsCount }} runs</div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($historyFilterOptions as $value => $label)
                                    <button type="button" x-on:click="historyFilter = @js($value)" @class([
                                        'rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.2em]',
                                        'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => $historyFilter === $value,
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $historyFilter !== $value,
                                    ])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/5 bg-black/40 p-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">quick commands</div>
                            <p class="mt-1 text-sm text-slate-400">one-click diagnostics for this server.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($quickCommands as $quickCommand)
                                    <button type="button" wire:click="executeQuickCommand(@js($quickCommand['command']))" class="group inline-flex flex-col items-start rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 text-left hover:border-cyan-400/40 hover:bg-slate-900">
                                        <span class="text-sm font-semibold text-white group-hover:text-cyan-200">{{ $quickCommand['label'] }}</span>
                                        <span class="mt-1 text-xs text-slate-400">{{ $quickCommand['description'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">shell presets</div>
                                    <p class="mt-1 text-sm text-slate-300">save reusable snippets and organize them by folder or tags.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ collect($presetGroups)->sum(fn ($group) => is_countable($group['presets'] ?? null) ? count($group['presets']) : 0) }} saved</div>
                            </div>
                            <div class="mt-3 grid gap-2">
                                <input x-model="presetSearch" type="search" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Search presets, folders, or tags" />
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-on:click="presetGroupFilter = ''" @class([
                                        'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                        'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => blank($presetGroupFilter),
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => filled($presetGroupFilter),
                                    ])>All folders</button>
                                    @foreach ($presetGroupOptions as $groupOption)
                                        <button type="button" x-on:click="presetGroupFilter = @js($groupOption)" @class([
                                            'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                            'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => $presetGroupFilter === $groupOption,
                                            'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $presetGroupFilter !== $groupOption,
                                        ])>{{ $groupOption }}</button>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-on:click="presetTagFilter = ''" @class([
                                        'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                        'border-amber-400/30 bg-amber-500/15 text-amber-100' => blank($presetTagFilter),
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => filled($presetTagFilter),
                                    ])>All tags</button>
                                    @foreach ($presetTagOptions as $tagOption)
                                        <button type="button" x-on:click="presetTagFilter = @js($tagOption)" @class([
                                            'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                            'border-amber-400/30 bg-amber-500/15 text-amber-100' => $presetTagFilter === $tagOption,
                                            'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $presetTagFilter !== $tagOption,
                                        ])>#{{ $tagOption }}</button>
                                    @endforeach
                                </div>
                                <input wire:model.live.debounce.250ms="presetName" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Preset name" />
                                <input wire:model.live.debounce.250ms="presetGroup" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Folder / group (optional)" />
                                <input wire:model.live.debounce.250ms="presetTags" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Tags, comma separated" />
                                <textarea wire:model.live.debounce.250ms="presetDescription" rows="2" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Optional description"></textarea>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" wire:click="savePreset" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400">{{ $editingPresetId ? 'update preset' : 'save preset' }}</button>
                                <button type="button" wire:click="resetPresetForm" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">clear</button>
                                <button type="button" wire:click="resetPresetFilters" class="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-100 hover:bg-amber-400/20">reset filters</button>
                            </div>
                        </div>
                    </aside>

                    <section class="flex min-h-0 flex-col gap-4">
                        <x-terminal-panel
                            eyebrow="xterm.js terminal"
                            title="server shell"
                            subtitle="type commands directly into the terminal. live output streams while queued jobs run in the background."
                            :status="'live streaming'"
                            status-tone="cyan"
                        >
                            <x-slot:actions>
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
                                    x-cloak
                                    :class="bridgeStatusClasses()"
                                >
                                    <span
                                        x-show="bridgeState === 'connecting' || bridgeState === 'reconnecting'"
                                        x-cloak
                                        class="inline-flex h-2.5 w-2.5 items-center justify-center rounded-full border border-current/40"
                                    >
                                        <span class="h-1.5 w-1.5 animate-spin rounded-full border border-current border-t-transparent"></span>
                                    </span>
                                    <span
                                        x-show="bridgeState !== 'connecting' && bridgeState !== 'reconnecting'"
                                        x-cloak
                                        class="h-2 w-2 rounded-full"
                                        :class="bridgeDotClasses()"
                                    ></span>
                                    <span x-text="bridgeStatusLabel()"></span>
                                </span>
                            </x-slot:actions>

                            <div wire:ignore class="relative min-h-0 flex-1">
                                <div
                                    x-ref="terminal"
                                    class="h-full min-h-[26rem] w-full overflow-hidden rounded-2xl border border-white/5 bg-slate-950 transition-shadow duration-300"
                                    :class="bridgeState === 'connected' ? 'shadow-[0_0_0_1px_rgba(16,185,129,0.18),0_0_32px_rgba(16,185,129,0.08)] ring-1 ring-emerald-400/15' : bridgeState === 'reconnecting' ? 'shadow-[0_0_0_1px_rgba(251,191,36,0.16),0_0_32px_rgba(251,191,36,0.08)] ring-1 ring-amber-400/15' : 'shadow-none'"
                                ></div>
                                <div
                                    x-show="bridgeToast"
                                    x-cloak
                                    class="pointer-events-none absolute right-6 top-6 z-20 rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] backdrop-blur-xl"
                                    :class="bridgeStatusClasses()"
                                    x-text="bridgeToast"
                                ></div>
                                <div x-show="autocompleteHint" x-cloak class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100" x-text="autocompleteHint"></div>
                            </div>
                        </x-terminal-panel>

                        <div class="space-y-3 rounded-3xl border border-white/5 bg-black/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">recent command cards</div>
                                    <p class="mt-1 text-sm text-slate-400">a compact record view stays below the terminal for quick scanning.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $runsCount }} runs</div>
                            </div>

                            <div class="grid gap-3 xl:grid-cols-2">
                                @forelse ($runs as $run)
                                    <details @class([
                                        'group rounded-2xl border border-white/5 bg-slate-900/70 px-4 py-4 transition-all duration-300',
                                        'border-cyan-400/30 bg-cyan-400/10 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)] ring-1 ring-cyan-400/25' => $run['status'] === 'running',
                                    ]) {{ $run['status'] === 'running' ? 'open' : '' }}>
                                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.24em] text-slate-400">
                                            <span class="inline-flex items-center gap-2">
                                                @if ($run['status'] === 'running')<span class="inline-flex h-2.5 w-2.5 rounded-full bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.9)] animate-pulse"></span>@endif
                                                <span>[{{ $run['started_at']?->format('H:i:s') ?? '--:--:--' }}]</span>
                                            </span>
                                            <span class="text-cyan-300">$</span>
                                            <span class="font-semibold text-white">{{ $run['command'] }}</span>
                                            <span @class([
                                                'rounded-full px-2.5 py-1 font-semibold',
                                                'bg-emerald-500/15 text-emerald-300' => $run['status'] === 'successful',
                                                'bg-cyan-500/15 text-cyan-300' => $run['status'] === 'running',
                                                'bg-rose-500/15 text-rose-300' => $run['status'] === 'failed',
                                                'bg-slate-500/15 text-slate-300' => ! in_array($run['status'], ['successful', 'running', 'failed'], true),
                                            ])>{{ $run['status'] }}</span>
                                            <span class="text-slate-500">{{ $run['duration_label'] }}</span>
                                        </summary>

                                        <div class="mt-3 rounded-xl border border-white/5 bg-black/50 px-4 py-3">
                                            <div class="space-y-2 text-sm text-slate-300">
                                                <div><span class="font-semibold text-slate-100">exit code:</span> {{ $run['exit_code'] ?? 'pending' }}</div>
                                                @if (filled($run['error_message']))
                                                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-rose-100">{{ $run['error_message'] }}</div>
                                                @endif
                                            </div>

                                            @if (filled($run['output']))
                                                <pre class="mt-3 whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $run['output'] }}</pre>
                                            @else
                                                <div class="mt-3 text-slate-500">no terminal output yet.</div>
                                            @endif
                                        </div>
                                    </details>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-cyan-400/20 bg-black/30 px-4 py-6 text-slate-400">no terminal commands have run yet. use the terminal or the quick commands to start a session.</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
