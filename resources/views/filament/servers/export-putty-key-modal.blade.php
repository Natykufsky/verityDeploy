<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/60 p-4 text-sm text-slate-300 dark:border-white/10">
        <p class="font-medium text-slate-100">Export a PuTTY-compatible private key</p>
        <p class="mt-2">
            The saved SSH private key is converted to PuTTY's <code class="rounded bg-slate-900 px-1.5 py-0.5 text-xs text-slate-100">.ppk</code>
            format for Windows SSH tools. Copy it immediately after export.
        </p>
    </div>

    @if(filled($generatedPuTTYKey))
        <div class="space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-100">PuTTY key ready</h3>
                    <p class="mt-1 text-sm text-slate-400">Copy the exported key and keep it secure.</p>
                </div>

                <x-filament::button
                    size="sm"
                    color="gray"
                    x-data="{ key: @js($generatedPuTTYKey) }"
                    x-on:click="navigator.clipboard.writeText(key)"
                >
                    Copy PuTTY key
                </x-filament::button>

                <x-filament::button
                    size="sm"
                    color="primary"
                    x-data="{ key: @js($generatedPuTTYKey), filename: @js(($record->name ?? 'server').'-private-key.ppk') }"
                    x-on:click="
                        const blob = new Blob([key], { type: 'text/plain;charset=utf-8' });
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(url);
                    "
                >
                    Download .ppk
                </x-filament::button>
            </div>

            <textarea
                readonly
                class="min-h-40 w-full rounded-2xl border border-slate-200/10 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100 shadow-inner outline-none focus:border-sky-500/50 dark:border-white/10"
            >{{ $generatedPuTTYKey }}</textarea>
        </div>
    @else
        <div class="flex flex-col gap-4 rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-100">No PuTTY key exported yet</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Use the action below to convert the saved SSH private key into PuTTY format.
                </p>
            </div>

            <x-filament::button wire:click="exportPuTTYKey" wire:loading.attr="disabled">
                Export PuTTY key
            </x-filament::button>
        </div>
    @endif
</div>
