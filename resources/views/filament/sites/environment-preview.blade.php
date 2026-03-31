@php
    $preview = $preview ?? [];
    $hasOverride = (bool) ($preview['has_override'] ?? false);
    $diff = $preview['diff'] ?? [];
    $generatedLines = $preview['generated_lines'] ?? [];
    $effectiveLines = $preview['effective_lines'] ?? [];
    $customLines = $preview['custom_lines'] ?? [];
@endphp

<div class="space-y-4 rounded-3xl border border-amber-400/20 bg-gradient-to-br from-slate-950 via-slate-950 to-amber-500/10 p-5 shadow-[0_24px_80px_-32px_rgba(0,0,0,.85)]">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-amber-200">
                Live .env preview
            </div>
            <div>
                <h3 class="text-xl font-semibold tracking-tight text-white">Compare generated values before you save</h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    This preview updates as you edit the key/value environment editor or paste a custom shared
                    <span class="font-semibold text-white">.env</span> override.
                </p>
            </div>
        </div>

        <div class="grid gap-2 text-left sm:grid-cols-3 lg:min-w-[30rem] lg:text-right">
            <div class="rounded-2xl border border-slate-200/10 bg-slate-950/80 px-3 py-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Source</div>
                <div class="mt-1 text-sm font-semibold text-white">{{ $hasOverride ? 'Custom override' : 'Generated' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200/10 bg-slate-950/80 px-3 py-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Generated lines</div>
                <div class="mt-1 text-sm font-semibold text-white">{{ count($generatedLines) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200/10 bg-slate-950/80 px-3 py-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Effective lines</div>
                <div class="mt-1 text-sm font-semibold text-white">{{ count($effectiveLines) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/10 bg-slate-950/60 px-4 py-3 text-sm leading-6 text-slate-300">
        @if ($hasOverride)
            <span class="font-semibold text-amber-200">Custom override enabled.</span>
            The generated environment variables are still shown below for comparison, but the override text is what
            will be written to the shared <span class="font-semibold text-white">.env</span> file.
        @else
            <span class="font-semibold text-emerald-200">Generated mode active.</span>
            The key/value editor below is the source of truth and will be written to the shared
            <span class="font-semibold text-white">.env</span> file.
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Generated preview</div>
                    <div class="mt-1 text-sm text-slate-300">Built from the environment variables editor.</div>
                </div>
                <div class="rounded-full border border-slate-200/10 bg-slate-900/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    {{ count($generatedLines) }} lines
                </div>
            </div>

            <pre class="mt-4 max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $preview['generated_contents'] ?: "No generated lines yet.\nAdd environment variables to see the preview." }}</pre>
        </div>

        <div class="rounded-2xl border border-amber-400/25 bg-amber-500/10 p-4 text-amber-50">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-amber-200">
                        {{ $hasOverride ? 'Custom override' : 'Effective .env' }}
                    </div>
                    <div class="mt-1 text-sm leading-6 text-amber-50/90">
                        {{ $hasOverride ? 'This text will replace the generated preview.' : 'This is the final .env file that will be written.' }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="rounded-full border border-amber-200/20 bg-amber-950/30 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-amber-100">
                        {{ count($effectiveLines) }} lines
                    </div>
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        @click="navigator.clipboard.writeText(@js($preview['effective_contents'] ?: '')).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
                        class="inline-flex items-center gap-2 rounded-full border border-amber-200/20 bg-slate-950/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-amber-100 transition hover:bg-slate-900"
                    >
                        <span x-show="!copied">Copy effective .env</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>
            </div>

            <pre class="mt-4 max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-xl border border-amber-200/15 bg-slate-950/80 px-4 py-3 font-mono text-xs leading-6 text-amber-50">{{ $preview['effective_contents'] ?: "The effective .env file will appear here.\nAdd values or a custom override to preview the final output." }}</pre>
        </div>
    </div>

    @if ($hasOverride)
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-emerald-50">
                <div class="text-xs uppercase tracking-[0.2em] text-emerald-200">Only in override</div>
                <div class="mt-1 text-sm text-emerald-50/80">Keys present in the custom file but missing from the generated preview.</div>
                <div class="mt-4 space-y-2">
                    @forelse ($diff['added'] ?? [] as $item)
                        <div class="rounded-xl border border-emerald-200/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border border-emerald-300/30 bg-emerald-400/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-100">
                                    {{ $item['key'] }}
                                </span>
                                <span class="text-xs text-emerald-50/70">Added</span>
                            </div>
                            <div class="mt-2 font-mono text-xs text-emerald-100">{{ $item['value'] }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-emerald-200/20 bg-slate-950/40 px-3 py-2 text-sm text-emerald-100/80">
                            No extra keys in the override.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 text-slate-100">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Only in generated</div>
                <div class="mt-1 text-sm text-slate-300">Keys that disappear when the custom override is active.</div>
                <div class="mt-4 space-y-2">
                    @forelse ($diff['removed'] ?? [] as $item)
                        <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border border-slate-300/20 bg-slate-700/30 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-100">
                                    {{ $item['key'] }}
                                </span>
                                <span class="text-xs text-slate-400">Removed</span>
                            </div>
                            <div class="mt-2 font-mono text-xs text-slate-300">{{ $item['value'] }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2 text-sm text-slate-300">
                            No generated keys are being removed.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-amber-400/25 bg-amber-500/10 p-4 text-amber-50">
                <div class="text-xs uppercase tracking-[0.2em] text-amber-200">Changed values</div>
                <div class="mt-1 text-sm text-amber-50/80">Keys that exist in both places but do not match.</div>
                <div class="mt-4 space-y-2">
                    @forelse ($diff['changed'] ?? [] as $item)
                        <div class="rounded-xl border border-amber-200/20 bg-slate-950/40 px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border border-amber-300/30 bg-amber-400/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-100">
                                    {{ $item['key'] }}
                                </span>
                                <span class="text-xs text-amber-50/70">Changed</span>
                            </div>
                            <div class="mt-2 space-y-1 font-mono text-xs leading-5 text-amber-100/80">
                                <div><span class="font-semibold text-emerald-300">Generated:</span> {{ $item['generated'] }}</div>
                                <div><span class="font-semibold text-amber-300">Override:</span> {{ $item['custom'] }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-amber-200/20 bg-slate-950/40 px-3 py-2 text-sm text-amber-100/80">
                            {{ $diff['message'] ?? 'No changed keys detected.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
