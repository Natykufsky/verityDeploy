<div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Command copy toolbar</p>
            <h3 class="text-lg font-semibold text-white">Copy common deployment commands</h3>
            <p class="max-w-2xl text-sm leading-6 text-slate-400">
                Use these one-click snippets to inspect the release, reinstall dependencies, run migrations, and restart workers without digging through the guide.
            </p>
        </div>

        <div class="text-xs text-slate-400">
            {{ $deployment->site->name }}
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-white/5 bg-black/20 p-4">
        <p class="text-xs uppercase tracking-[0.24em] text-slate-500">
            Quick actions
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
            @forelse ($snippets as $index => $snippet)
                <button
                    type="button"
                    x-data="{ copied: false }"
                    x-on:click="
                        navigator.clipboard.writeText(@js($snippet['command']));
                        copied = true;
                        setTimeout(() => copied = false, 1500);
                    "
                    class="group rounded-full border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-semibold text-slate-100 transition hover:border-emerald-400/30 hover:bg-emerald-500/10"
                >
                    <span class="block text-[11px] uppercase tracking-[0.22em] text-slate-500">
                        {{ $snippet['title'] }}
                    </span>
                    <span class="mt-1 block text-slate-200 group-hover:text-emerald-200">
                        {{ $snippet['usage'] }}
                    </span>
                    <span class="mt-2 inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-300">
                        <span x-show="!copied">Copy command</span>
                        <span x-show="copied">Copied</span>
                    </span>
                </button>
            @empty
                <div class="rounded-xl border border-white/5 bg-black/20 px-4 py-3 text-sm text-slate-400">
                    No command snippets are available for this deployment yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
