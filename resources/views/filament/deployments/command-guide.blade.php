<div id="command-guide" class="deployment-frost-card space-y-4 rounded-2xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Command guide</span>
                <x-info-tooltip text="Reference commands used by the deployment workflow." label="Command guide help" />
            </div>
            <h3 class="text-lg font-semibold text-white">Useful deployment commands</h3>
            <div class="flex items-start gap-2">
                <p class="max-w-3xl text-sm leading-6 text-slate-400">
                    {{ $record->command_guide_intro }}
                </p>
                <x-info-tooltip text="Explains what the commands are used for during deployment." label="Command guide intro help" />
            </div>
        </div>
        <div class="rounded-2xl border border-white/5 bg-black/20 px-3 py-2 text-xs text-slate-400">
            {{ $record->steps()->count() }} step{{ $record->steps()->count() === 1 ? '' : 's' }} available for inspection
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($record->command_guide_snippets as $snippet)
                    <article class="deployment-frost-panel rounded-2xl p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-white">{{ $snippet['title'] }}</h4>
                            <x-info-tooltip text="This command snippet is part of the deployment reference guide." label="Snippet help" />
                        </div>
                        <div class="flex items-start gap-2">
                            <p class="text-sm leading-6 text-slate-400">{{ $snippet['description'] }}</p>
                        </div>
                    </div>
                    <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                        {{ $snippet['usage'] }}
                    </span>
                </div>

                <div class="deployment-frost-panel mt-4 rounded-xl px-4 py-3 font-mono text-xs leading-6 text-slate-100">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-[10px] uppercase tracking-[0.2em] text-emerald-300">
                            <span>Command</span>
                            <x-info-tooltip text="The exact command shown in this snippet." label="Command help" />
                        </div>
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($snippet['command'])); copied = true; setTimeout(() => copied = false, 1200);"
                            class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300 transition hover:border-emerald-400/30 hover:bg-emerald-500/10 hover:text-emerald-200"
                        >
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied">Copied</span>
                        </button>
                    </div>
                    <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-words">{{ $snippet['command'] }}</pre>
                </div>
            </article>
        @endforeach
    </div>
</div>
