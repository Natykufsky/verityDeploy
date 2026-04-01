<div id="site-terminal" class="scroll-mt-28 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-100">
                site terminal
            </div>
            <h3 class="text-2xl font-semibold tracking-tight text-white">{{ $site->name }}</h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-400">
                commands automatically start inside
                <span class="font-mono text-slate-100">{{ $sitePath ?? $site->deploy_path }}</span>.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                {{ $site->server?->name ?? 'No server' }}
            </span>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                {{ $site->deploy_source }}
            </span>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                auto-cd
            </span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ($quickCommands as $quickCommand)
            <button
                type="button"
                wire:click="runCommandFromTerminal(@js($quickCommand['command']))"
                class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-left transition hover:border-cyan-400/30 hover:bg-cyan-500/10"
            >
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-white">{{ $quickCommand['label'] }}</span>
                <span class="text-[11px] leading-5 text-slate-400">{{ $quickCommand['description'] }}</span>
            </button>
        @endforeach
    </div>

    <x-terminal-panel
        class="deployment-frost-panel"
        eyebrow="terminal"
        :title="$site->name"
        :subtitle="'the console stays inside the site folder and streams live output while queued jobs run.'"
        status="session"
        status-tone="cyan"
        x-data="{
            terminal: null,
            feedUrl: @js(route('sites.terminal-feed', ['record' => $site->id])),
            componentId: @js($this->getId()),
            promptText: @js($terminalPrompt),
            welcomeTitle: @js('verityDeploy site terminal'),
            welcomeText: @js('Connected to the site console. Commands automatically start inside the site folder. Press Tab for autocomplete.'),
        }"
        x-init="terminal = verityServerTerminal({ componentId, feedUrl, promptText, welcomeTitle, welcomeText }); terminal.init()"
    >
        <x-slot name="actions">
            <button
                type="button"
                wire:click="resetTerminalWorkspace"
                class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10"
            >
                reset workspace
            </button>
        </x-slot>

        <div wire:ignore>
            <div x-ref="terminal" class="h-[24rem] w-full overflow-hidden rounded-2xl border border-white/5 bg-slate-950"></div>
            <div x-show="autocompleteHint" x-cloak class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100" x-text="autocompleteHint"></div>
        </div>
    </x-terminal-panel>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">recent command cards</div>
                <p class="mt-1 text-sm text-slate-400">a short history helps you see what ran from this site folder.</p>
            </div>
            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $runsCount }} runs</div>
        </div>

        <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
            @forelse ($runs as $run)
                <details @class([
                    'group rounded-2xl border border-white/5 bg-black/20 px-4 py-3 transition-all duration-300',
                    'ring-1 ring-cyan-400/30 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)]' => $run['status'] === 'running',
                ]) {{ $run['status'] === 'running' ? 'open' : '' }}>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 text-[11px] uppercase tracking-[0.24em] text-slate-400">
                        <span class="inline-flex items-center gap-2">
                            @if ($run['status'] === 'running')
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.9)] animate-pulse"></span>
                            @endif
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

                    <div class="mt-3 rounded-xl border border-white/5 bg-black/50 px-3 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-300">
                            <div><span class="font-semibold text-slate-100">Exit code:</span> {{ $run['exit_code'] ?? 'pending' }}</div>
                            @if (filled($run['error_message']))
                                <div class="rounded-full border border-rose-500/20 bg-rose-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-100">
                                    failed
                                </div>
                            @endif
                        </div>

                        @if (filled($run['error_message']))
                            <div class="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-rose-100">{{ $run['error_message'] }}</div>
                        @endif

                        @if (filled($run['output']))
                            <div class="mt-3 max-h-[11rem] overflow-y-auto rounded-xl border border-white/5 bg-slate-950 px-3 py-3">
                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ $run['output'] }}</pre>
                            </div>
                        @else
                            <div class="mt-3 text-slate-500">no terminal output yet.</div>
                        @endif
                    </div>
                </details>
            @empty
                <div class="rounded-2xl border border-dashed border-cyan-400/20 bg-black/30 px-4 py-6 text-slate-400">
                    no site terminal commands have run yet. use the terminal or the quick commands above to start a session.
                </div>
            @endforelse
        </div>
    </div>
</div>
