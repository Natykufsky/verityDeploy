<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/60 p-4 text-sm text-slate-300 dark:border-white/10">
        <p class="font-medium text-slate-100">Generate a fresh Ed25519 key pair</p>
        <p class="mt-2">
            The private key is stored encrypted on the server record. The public key can be copied into the target server's
            <code class="rounded bg-slate-900 px-1.5 py-0.5 text-xs text-slate-100">authorized_keys</code>
            file.
        </p>
    </div>

    @if(filled($generatedPublicKey))
        <div class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-100">Public key ready</h3>
                    <p class="mt-1 text-sm text-slate-400">Copy this key and add it to the remote server.</p>
                </div>

                <x-filament::button
                    size="sm"
                    color="gray"
                    x-data="{ key: @js($generatedPublicKey) }"
                    x-on:click="navigator.clipboard.writeText(key)"
                >
                    Copy public key
                </x-filament::button>
            </div>

            <textarea
                readonly
                class="min-h-40 w-full rounded-2xl border border-slate-200/10 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100 shadow-inner outline-none focus:border-sky-500/50 dark:border-white/10"
            >{{ $generatedPublicKey }}</textarea>
        </div>
    @else
        <div class="flex flex-col gap-4 rounded-2xl border border-dashed border-slate-200/10 bg-slate-950/40 p-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-100">No key generated yet</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Use the action below to generate a new key pair and reveal the public key here.
                </p>
            </div>

            <x-filament::button wire:click="generateSshKey" wire:loading.attr="disabled">
                Generate key pair
            </x-filament::button>
        </div>
    @endif
</div>
