<div id="site-files" class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-100">
                file manager
            </div>
            <h3 class="text-2xl font-semibold tracking-tight text-white">{{ $site->name }}</h3>
            <p class="max-w-3xl text-sm leading-6 text-slate-400">
                Browse and edit files inside
                <span class="font-mono text-slate-100">{{ $rootPath }}</span>.
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
                read / edit
            </span>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="deployment-frost-card rounded-2xl border border-white/5 bg-black/20 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">directory browser</div>
                    <p class="mt-1 text-sm text-slate-400">Select a folder or open a file to edit it.</p>
                </div>
                <button
                    type="button"
                    wire:click="navigate('')"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10"
                >
                    root
                </button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                @foreach ($breadcrumbs as $crumb)
                    <button
                        type="button"
                        wire:click="navigate(@js($crumb['path']))"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-2 font-semibold uppercase tracking-[0.2em] text-slate-300 hover:border-amber-400/30 hover:bg-amber-500/10"
                    >
                        {{ $crumb['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-4 max-h-96 space-y-2 overflow-y-auto pr-1">
                @forelse ($items as $item)
                    <button
                        type="button"
                        wire:click="openItem(@js($item['relative_path']), @js($item['type']))"
                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-white/5 bg-slate-950/70 px-4 py-3 text-left transition hover:border-amber-400/30 hover:bg-amber-500/10"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">
                                {{ $item['type'] === 'directory' ? '📁' : '📄' }} {{ $item['name'] }}
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                {{ $item['relative_path'] ?: $item['name'] }}
                            </div>
                        </div>
                        <div class="text-right text-[11px] uppercase tracking-[0.2em] text-slate-500">
                            <div>{{ $item['type'] }}</div>
                            @if ($item['type'] === 'file' && filled($item['size']))
                                <div>{{ number_format((int) $item['size']) }} bytes</div>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-amber-500/20 bg-black/30 px-4 py-6 text-slate-400">
                        This directory is empty.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="deployment-frost-card rounded-2xl border border-white/5 bg-black/20 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">code editor</div>
                    <p class="mt-1 text-sm text-slate-400">Open a text file, edit it, and save changes back to the server.</p>
                </div>
                <div class="text-xs text-slate-500">
                    {{ filled($selectedFile) ? $selectedFile : 'No file selected' }}
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <textarea
                    wire:model="editorContents"
                    class="min-h-96 w-full rounded-2xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-sm leading-6 text-slate-100 outline-none ring-0 focus:border-amber-400/40"
                    placeholder="Open a file to edit its contents here..."
                    @disabled(blank($selectedFile))
                ></textarea>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs text-slate-500">
                        {{ filled($selectedFile) ? 'Editing enabled for this file.' : 'Pick a file from the browser to begin.' }}
                    </div>
                    <button
                        type="button"
                        wire:click="saveFile"
                        @disabled(blank($selectedFile))
                        class="rounded-full border border-amber-400/20 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-100 hover:bg-amber-500/25 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Save file
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
