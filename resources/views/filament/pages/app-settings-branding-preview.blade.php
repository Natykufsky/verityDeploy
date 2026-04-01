<div class="rounded-2xl border border-white/5 bg-slate-950/90 p-5 text-slate-100 shadow-lg shadow-slate-950/20">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div>
            <p class="text-[11px] uppercase tracking-[0.28em] text-amber-300/80">Brand preview</p>
            <h3 class="mt-1 text-base font-semibold text-white">How the panel branding reads</h3>
        </div>
        <p class="text-xs text-slate-400">This updates as you edit the branding fields.</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
        <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    @if (filled($logoUrl))
                        <img src="{{ $logoUrl }}" alt="App logo" class="h-full w-full object-contain p-2" />
                    @else
                        <span class="text-sm font-black tracking-[0.2em] text-amber-200">VD</span>
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-white">{{ $appName }}</p>
                    <p class="text-xs uppercase tracking-[0.22em] text-slate-500">primary logo</p>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-white/5 bg-slate-950/80 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg border border-white/10 bg-slate-900">
                        @if (filled($faviconUrl))
                            <img src="{{ $faviconUrl }}" alt="Favicon" class="h-full w-full object-contain p-1" />
                        @else
                            <span class="text-[10px] font-bold tracking-[0.2em] text-slate-500">ICON</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Browser favicon</p>
                        <p class="text-sm text-slate-300">{{ filled($faviconUrl) ? 'Uploaded favicon is active.' : 'No favicon uploaded yet.' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-3">
            <div class="rounded-2xl border border-white/5 bg-black/20 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Tagline</p>
                <p class="mt-1 text-sm text-slate-200">{{ filled($tagline) ? $tagline : 'No tagline set yet.' }}</p>
            </div>
            <div class="rounded-2xl border border-white/5 bg-black/20 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Description</p>
                <p class="mt-1 text-sm leading-7 text-slate-300">{{ filled($description) ? $description : 'No description set yet.' }}</p>
            </div>
            <div class="rounded-2xl border border-white/5 bg-black/20 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Support URL</p>
                <p class="mt-1 text-sm text-slate-300">
                    {{ filled($supportUrl) ? $supportUrl : 'No support URL configured.' }}
                </p>
            </div>
        </div>
    </div>
</div>
